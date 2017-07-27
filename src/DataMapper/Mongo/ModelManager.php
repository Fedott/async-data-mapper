<?php declare(strict_types=1);

namespace Fedot\DataMapper\Mongo;

use function Amp\call;
use function Amp\Promise\all;
use Amp\Promise;
use Doctrine\Instantiator\Instantiator;
use Fedot\DataMapper\IdentityMap;
use Fedot\DataMapper\Metadata\ClassMetadata;
use Fedot\DataMapper\Metadata\PropertyMetadata;
use Fedot\DataMapper\ModelManagerInterface;
use Metadata\MetadataFactory;
use MongoDB\Database;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class ModelManager implements ModelManagerInterface
{
    /**
     * @var MetadataFactory
     */
    private $metadataFactory;

    /**
     * @var Database
     */
    private $database;

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
        Database $database,
        PropertyAccessorInterface $propertyAccessor,
        Instantiator $instantiator
    ) {
        $this->metadataFactory = $metadataFactory;
        $this->database = $database;
        $this->propertyAccessor = $propertyAccessor;
        $this->instantiator = $instantiator;
    }

    public function persist($model, IdentityMap $identityMap = null): Promise
    {
        return call(function ($model, ?IdentityMap $identityMap) {
            if (null === $identityMap) {
                $identityMap = new IdentityMap();
            }

            /** @var ClassMetadata $classMetadata */
            $classMetadata = $this->metadataFactory->getMetadataForClass(get_class($model));

            $this->database->selectCollection($this->getCollectionNameByClassName($classMetadata->name))
                ->updateOne(
                    ['id' => $this->getIdFromModel($classMetadata, $model)],
                    ['$set' => $this->getModelData($classMetadata, $model)],
                    ['upsert' => true]
                );

            $identityMap->add($classMetadata, $this->getIdFromModel($classMetadata, $model), $model);

            return true;
        }, $model, $identityMap);
    }

    public function remove($model, IdentityMap $identityMap = null): Promise
    {
        return call(function ($model, ?IdentityMap $identityMap) {
            if (null === $identityMap) {
                $identityMap = new IdentityMap();
            }

            /** @var ClassMetadata $classMetadata */
            $classMetadata = $this->metadataFactory->getMetadataForClass(get_class($model));

            $this->database
                ->selectCollection($this->getCollectionNameByClassName($classMetadata->name))
                ->deleteOne(['id' => $this->getIdFromModel($classMetadata, $model)])
            ;

            $identityMap->delete($classMetadata, $this->getIdFromModel($classMetadata, $model));

            return true;
        }, $model, $identityMap);
    }

    public function find(string $class, string $id, int $depthLevel = 1, IdentityMap $identityMap = null): Promise
    {
        return call(function (string $class, string $id, int $depthLevel = 1, ?IdentityMap $identityMap) {
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

                $modelData = $this->database
                    ->selectCollection($this->getCollectionNameByClassName($classMetadata->name))
                    ->findOne(['id' => $id])
                ;

                if (empty($modelData)) {
                    $modelInstance = null;
                } else {
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
                                        ksort($referenceModels);
                                    }

                                    $propertyMetadata->setValue($modelInstance, $referenceModels);
                                }
                            }
                        } elseif (
                            !array_key_exists($propertyMetadata->name, $modelData)
                            && $propertyMetadata->referenceType === 'many'
                        ) {
                            $propertyMetadata->setValue($modelInstance, []);
                        }
                    }
                }
            }

            return $modelInstance;
        }, $class, $id, $depthLevel, $identityMap);
    }

    private function getCollectionNameByClassName(string $className): string
    {
        return strtolower($className);
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