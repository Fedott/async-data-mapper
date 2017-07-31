<?php declare(strict_types=1);

namespace Fedot\DataMapper\Redis;

use Amp\Promise;
use Amp\Redis\Client;
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
     * @var Client
     */
    private $redisClient;

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
        Client $redisClient,
        PropertyAccessorInterface $propertyAccessor,
        InstantiatorInterface $instantiator
    ) {
        $this->metadataFactory = $metadataFactory;
        $this->redisClient = $redisClient;
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
        return $this->redisClient->hmSet(
            $this->getKey($classMetadata, $model),
            $this->getModelData($classMetadata, $model)
        );
    }

    protected function removeModel(ClassMetadata $classMetadata, $model): Promise
    {
        return $this->redisClient->del($this->getKey($classMetadata, $model));
    }

    protected function findRawModel(ClassMetadata $classMetadata, string $id): Promise
    {
        return $this->redisClient->hGetAll($this->getKeyByClassNameId($classMetadata->name, $id));
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
}
