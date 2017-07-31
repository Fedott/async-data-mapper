<?php declare(strict_types=1);

namespace Fedot\DataMapper\Memory;

use Amp\Promise;
use Amp\Success;
use Doctrine\Instantiator\InstantiatorInterface;
use Fedot\DataMapper\AbstractModelManager;
use Fedot\DataMapper\Metadata\ClassMetadata;
use Metadata\MetadataFactory;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class ModelManager extends AbstractModelManager
{
    /**
     * @var MetadataFactory
     */
    private $metadataFactory;

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

    /**
     * @var array
     */
    private $data = [];

    public function __construct(
        MetadataFactory $metadataFactory,
        PropertyAccessorInterface $propertyAccessor,
        InstantiatorInterface $instantiator,
        int $maxDepthLevel
    ) {
        $this->metadataFactory = $metadataFactory;
        $this->propertyAccessor = $propertyAccessor;
        $this->instantiator = $instantiator;
        $this->maxDepthLevel = $maxDepthLevel;
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
        $this->data[$classMetadata->name][$this->getIdFromModel($classMetadata, $model)] = $this->getModelData($classMetadata, $model);

        return new Success();
    }

    protected function removeModel(ClassMetadata $classMetadata, $model): Promise
    {
        unset($this->data[$classMetadata->name][$this->getIdFromModel($classMetadata, $model)]);

        return new Success();
    }

    protected function findRawModel(ClassMetadata $classMetadata, string $id): Promise
    {
        return new Success(
            $this->data[$classMetadata->name][$id] ?? null
        );
    }
}
