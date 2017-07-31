<?php declare(strict_types=1);

namespace Fedot\DataMapper;

use Amp\Promise;
use Doctrine\Instantiator\InstantiatorInterface;
use Fedot\DataMapper\Metadata\ClassMetadata;
use Fedot\DataMapper\Metadata\PropertyMetadata;
use Metadata\MetadataFactory;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use function Amp\call;
use function Amp\Promise\all;

abstract class AbstractModelManager implements ModelManagerInterface
{
    abstract protected function upsertModel(ClassMetadata $classMetadata, $model): Promise;

    abstract protected function removeModel(ClassMetadata $classMetadata, $model): Promise;

    abstract protected function findRawModel(ClassMetadata $classMetadata, string $id): Promise;

    abstract protected function getMetadataFactory(): MetadataFactory;
    abstract protected function getPropertyAccessor(): PropertyAccessorInterface;
    abstract protected function getInstantiator(): InstantiatorInterface;
    abstract protected function getMaxDepth(): int;

    public function persist($model, IdentityMap $identityMap = null): Promise
    {
        return call(function ($model, ?IdentityMap $identityMap) {
            if (null === $identityMap) {
                $identityMap = new IdentityMap();
            }

            /** @var ClassMetadata $classMetadata */
            $classMetadata = $this->getMetadataFactory()->getMetadataForClass(get_class($model));

            yield $this->upsertModel($classMetadata, $model);

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
            $classMetadata = $this->getMetadataFactory()->getMetadataForClass(get_class($model));

            yield $this->removeModel($classMetadata, $model);

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
            $classMetadata = $this->getMetadataFactory()->getMetadataForClass($class);

            if ($identityMap->has($classMetadata, $id)) {
                $modelInstance = $identityMap->get($classMetadata, $id);
            } else {
                $modelInstance = $this->getInstantiator()->instantiate($class);

                $identityMap->add($classMetadata, $id, $modelInstance);

                $modelData = yield $this->findRawModel($classMetadata, $id);

                if (empty($modelData)) {
                    $modelInstance = null;
                } else {
                    /** @var PropertyMetadata $propertyMetadata */
                    foreach ($classMetadata->propertyMetadata as $propertyMetadata) {
                        if (array_key_exists($propertyMetadata->name, $modelData)) {
                            if ($propertyMetadata->isField) {
                                $propertyMetadata->setValue($modelInstance, $modelData[$propertyMetadata->name]);
                            } elseif ($depthLevel <= $this->getMaxDepth() && !empty($modelData[$propertyMetadata->name])) {
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

    protected function getModelData(ClassMetadata $classMetadata, $model): array
    {
        $data = [];

        /** @var PropertyMetadata $propertyMetadata */
        foreach ($classMetadata->propertyMetadata as $propertyMetadata) {
            if ($propertyMetadata->isField) {
                $data[$propertyMetadata->name] = $this->getPropertyAccessor()->getValue($model, $propertyMetadata->name);
            } elseif ($propertyMetadata->referenceType === 'one') {
                /** @var ClassMetadata $referenceClassMetadata */
                $referenceClassMetadata = $this->getMetadataFactory()->getMetadataForClass(
                    $propertyMetadata->referenceTarget
                );

                $referenceModel = $this->getPropertyAccessor()->getValue($model, $propertyMetadata->name);
                if (null !== $referenceModel) {
                    $referenceId = $this->getPropertyAccessor()->getValue($referenceModel, $referenceClassMetadata->idField);
                    $data[$propertyMetadata->name] = $referenceId;
                }
            } elseif ($propertyMetadata->referenceType === 'many') {
                /** @var ClassMetadata $referenceClassMetadata */
                $referenceClassMetadata = $this->getMetadataFactory()->getMetadataForClass(
                    $propertyMetadata->referenceTarget
                );

                $referenceModels = $this->getPropertyAccessor()->getValue($model, $propertyMetadata->name);

                $referenceIds = [];
                foreach ($referenceModels as $referenceModel) {
                    $referenceIds[] = $this->getPropertyAccessor()
                        ->getValue($referenceModel, $referenceClassMetadata->idField);
                }

                if (count($referenceIds) > 0) {
                    $data[$propertyMetadata->name] = implode(',', $referenceIds);
                }
            }
        }

        return $data;
    }

    protected function getIdFromModel(ClassMetadata $classMetadata, $model): string
    {
        return $this->getPropertyAccessor()->getValue($model, $classMetadata->idField);
    }
}
