<?php declare(strict_types=1);

namespace Fedot\DataMapper\Redis;

use Amp\Deferred;
use Amp\Redis\Client;
use AsyncInterop\Loop;
use AsyncInterop\Promise;
use Doctrine\Instantiator\Instantiator;
use Fedot\DataMapper\Metadata\ClassMetadata;
use Fedot\DataMapper\Metadata\PropertyMetadata;
use Fedot\DataMapper\ModelManagerInterface;
use Metadata\MetadataFactory;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use function Amp\wrap;

class ModelManager implements ModelManagerInterface
{
    /**
     * @var MetadataFactory
     */
    private $metadataFactory;

    /**
     * @var Client
     */
    private $redisClient;

    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var Instantiator
     */
    private $instantiator;

    /**
     * @var int
     */
    private $maxDepthLevel = 2;

    public function __construct(
        MetadataFactory $metadataFactory,
        Client $redisClient,
        PropertyAccessorInterface $propertyAccessor,
        Instantiator $instantiator
    ) {
        $this->metadataFactory = $metadataFactory;
        $this->redisClient = $redisClient;
        $this->propertyAccessor = $propertyAccessor;
        $this->instantiator = $instantiator;
    }

    public function persist($model): Promise
    {
        $deferred = new Deferred();

        Loop::defer(wrap(function () use ($deferred, $model) {
            /** @var ClassMetadata $classMetadata */
            $classMetadata = $this->metadataFactory->getMetadataForClass(get_class($model));

            yield $this->redisClient->hmSet(
                $this->getKey($classMetadata, $model),
                $this->getModelData($classMetadata, $model)
            );

            $deferred->resolve(true);
        }));

        return $deferred->promise();
    }

    public function find(string $class, string $id, int $depthLevel = 1): Promise
    {
        $deferred = new Deferred();

        Loop::defer(wrap(function () use ($deferred, $class, $id, $depthLevel) {
            $modelData = yield $this->redisClient->hGetAll($this->getKeyByClassNameId($class, $id));

            $modelInstance = $this->instantiator->instantiate($class);

            /** @var ClassMetadata $classMetadata */
            $classMetadata = $this->metadataFactory->getMetadataForClass($class);

            /** @var PropertyMetadata $propertyMetadata */
            foreach ($classMetadata->propertyMetadata as $propertyMetadata) {
                if (array_key_exists($propertyMetadata->name, $modelData)) {
                    if ($propertyMetadata->isField) {
                        $propertyMetadata->setValue($modelInstance, $modelData[$propertyMetadata->name]);
                    } elseif ($depthLevel <= $this->maxDepthLevel && !empty($modelData[$propertyMetadata->name])) {
                        if ($propertyMetadata->referenceType === 'one') {
                            $referenceModel = yield $this->find(
                                $propertyMetadata->referenceTarget,
                                $modelData[$propertyMetadata->name],
                                $depthLevel + 1
                            );
                            $propertyMetadata->setValue($modelInstance, $referenceModel);
                        } elseif ($propertyMetadata->referenceType === 'many') {
                            $referenceModels = [];
                            foreach (explode(',', $modelData[$propertyMetadata->name]) as $referenceId) {
                                $referenceModels[] = $this->find(
                                    $propertyMetadata->referenceTarget,
                                    $referenceId,
                                    $depthLevel + 1
                                );
                            }
                            $propertyMetadata->setValue($modelInstance, $referenceModels);
                        }
                    }
                }
            }

            $deferred->resolve($modelInstance);
        }));

        return $deferred->promise();
    }

    private function getKey(ClassMetadata $classMetadata, $model): string
    {
        $id = $this->propertyAccessor->getValue($model, $classMetadata->idField);

        return $this->getKeyByClassNameId($classMetadata->name, $id);
    }

    private function getKeyByClassNameId(string $className, string $id): string
    {
        return "entity:{$className}:$id";
    }

    private function getModelData(ClassMetadata $classMetadata, $model): array
    {
        $data = [];

        /** @var PropertyMetadata $propertyMetadata */
        foreach ($classMetadata->propertyMetadata as $propertyMetadata) {
            if ($propertyMetadata->isField) {
                $data[$propertyMetadata->name] = $this->propertyAccessor->getValue($model, $propertyMetadata->name);
            } elseif ($propertyMetadata->referenceType === 'one') {
                /** @var ClassMetadata $referenceClassMetadata */
                $referenceClassMetadata = $this->metadataFactory->getMetadataForClass(
                    $propertyMetadata->referenceTarget
                );

                $referenceModel = $this->propertyAccessor->getValue($model, $propertyMetadata->name);
                if (null !== $referenceModel) {
                    $referenceId = $this->propertyAccessor->getValue($referenceModel, $referenceClassMetadata->idField);
                    $data[$propertyMetadata->name] = $referenceId;
                }
            } elseif ($propertyMetadata->referenceType === 'many') {
                /** @var ClassMetadata $referenceClassMetadata */
                $referenceClassMetadata = $this->metadataFactory->getMetadataForClass(
                    $propertyMetadata->referenceTarget
                );

                $referenceModels = $this->propertyAccessor->getValue($model, $propertyMetadata->name);

                $referenceIds = [];
                foreach ($referenceModels as $referenceModel) {
                    $referenceIds[] = $this->propertyAccessor
                        ->getValue($referenceModel, $referenceClassMetadata->idField);
                }

                if (count($referenceIds) > 0) {
                    $data[$propertyMetadata->name] = implode(',', $referenceIds);
                }
            }
        }

        return $data;
    }
}
