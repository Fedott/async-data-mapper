<?php declare(strict_types=1);

namespace Fedot\DataMapper\Redis;

use function Amp\all;
use Amp\Deferred;
use Amp\Redis\Client;
use AsyncInterop\Loop;
use AsyncInterop\Promise;
use Doctrine\Instantiator\Instantiator;
use Fedot\DataMapper\IdentityMap;
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

    public function persist($model, IdentityMap $identityMap = null): Promise
    {
        $deferred = new Deferred();

        Loop::defer(wrap(function () use ($deferred, $model, $identityMap) {
            if (null === $identityMap) {
                $identityMap = new IdentityMap();
            }

            /** @var ClassMetadata $classMetadata */
            $classMetadata = $this->metadataFactory->getMetadataForClass(get_class($model));

            yield $this->redisClient->hmSet(
                $this->getKey($classMetadata, $model),
                $this->getModelData($classMetadata, $model)
            );

            $identityMap->add($classMetadata, $this->getIdFromModel($classMetadata, $model), $model);

            $deferred->resolve(true);
        }));

        return $deferred->promise();
    }

    public function find(string $class, string $id, int $depthLevel = 1, IdentityMap $identityMap = null): Promise
    {
        $deferred = new Deferred();

        Loop::defer(wrap(function () use ($deferred, $class, $id, $depthLevel, $identityMap) {
            if (null === $identityMap) {
                $identityMap = new IdentityMap();
            }

            /** @var ClassMetadata $classMetadata */
            $classMetadata = $this->metadataFactory->getMetadataForClass($class);

            if ($identityMap->has($classMetadata, $id)) {
                $modelInstance = $identityMap->get($classMetadata, $id);
            } else {
                $modelInstance = $this->instantiator->instantiate($class);

                $identityMap->add($classMetadata, $id, $modelInstance);

                $modelData = yield $this->redisClient->hGetAll($this->getKeyByClassNameId($class, $id));

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
                                    $depthLevel + 1,
                                    $identityMap
                                );
                                $propertyMetadata->setValue($modelInstance, $referenceModel);
                            } elseif ($propertyMetadata->referenceType === 'many') {
                                $referenceModels = [];
                                foreach (explode(',', $modelData[$propertyMetadata->name]) as $referenceId) {
                                    $referenceModels[] = $this->find(
                                        $propertyMetadata->referenceTarget,
                                        $referenceId,
                                        $depthLevel + 1,
                                        $identityMap
                                    );
                                }

                                if (count($referenceModels) > 0) {
                                    $referenceModels = yield all($referenceModels);
                                }

                                $propertyMetadata->setValue($modelInstance, $referenceModels);
                            }
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
        $id = $this->getIdFromModel($classMetadata, $model);

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

    private function getIdFromModel(ClassMetadata $classMetadata, $model): string
    {
        return $this->propertyAccessor->getValue($model, $classMetadata->idField);
    }
}
