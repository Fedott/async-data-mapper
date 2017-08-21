<?php declare(strict_types=1);

namespace Fedot\DataMapper\Mongo;

use Amp\Promise;
use Amp\Success;
use Doctrine\Instantiator\InstantiatorInterface;
use Fedot\DataMapper\AbstractModelManager;
use Fedot\DataMapper\Metadata\ClassMetadata;
use Metadata\MetadataFactory;
use MongoDB\Database;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class ModelManager extends AbstractModelManager
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
     * @var InstantiatorInterface
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
        InstantiatorInterface $instantiator
    ) {
        $this->metadataFactory = $metadataFactory;
        $this->database = $database;
        $this->propertyAccessor = $propertyAccessor;
        $this->instantiator = $instantiator;
    }

    protected function getMetadataFactory(): MetadataFactory
    {
        return $this->metadataFactory;
    }

    protected function getPropertyAccessor(): PropertyAccessorInterface
    {
        return $this->propertyAccessor;
    }

    protected function getInstantiator(): InstantiatorInterface
    {
        return $this->instantiator;
    }

    protected function getMaxDepth(): int
    {
        return $this->maxDepthLevel;
    }

    protected function upsertModel(ClassMetadata $classMetadata, $model): Promise
    {
        $this->database->selectCollection($this->getCollectionNameByClassName($classMetadata->name))
            ->updateOne(
                ['id' => $this->getIdFromModel($classMetadata, $model)],
                ['$set' => $this->getModelData($classMetadata, $model)],
                ['upsert' => true]
            );

        return new Success();
    }

    protected function removeModel(ClassMetadata $classMetadata, $model): Promise
    {
        $this->database
            ->selectCollection($this->getCollectionNameByClassName($classMetadata->name))
            ->deleteOne(['id' => $this->getIdFromModel($classMetadata, $model)])
        ;

        return new Success();
    }

    protected function findRawModel(ClassMetadata $classMetadata, string $id): Promise
    {
        $modelData = $this->database
            ->selectCollection($this->getCollectionNameByClassName($classMetadata->name))
            ->findOne(['id' => $id])
        ;

        return new Success($modelData);
    }

    private function getCollectionNameByClassName(string $className): string
    {
        return strtolower($className);
    }
}
