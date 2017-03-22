<?php declare(strict_types=1);
namespace Fedot\DataMapper\Redis;

use Amp\Deferred;
use Amp\Failure;
use Amp\Redis\Client;
use AsyncInterop\Loop;
use AsyncInterop\Promise;
use Fedot\DataMapper\Identifiable;
use Fedot\DataMapper\RelationshipManagerInterface;
use TypeError;
use function Amp\wrap;

class RelationshipManager implements RelationshipManagerInterface
{
    /**
     * @var KeyGenerator
     */
    protected $keyGenerator;

    /**
     * @var Client
     */
    protected $redisClient;

    public function __construct(KeyGenerator $keyGenerator, Client $redisClient)
    {
        $this->redisClient = $redisClient;
        $this->keyGenerator = $keyGenerator;
    }

    private function addToIndex(string $indexName, string $id): Promise
    {
        $promisor = new Deferred();

        Loop::defer(wrap(function () use ($promisor, $id, $indexName) {
            yield $this->redisClient->lRem($indexName, $id, 0);

            yield $this->redisClient->lPush($indexName, $id);

            $promisor->resolve(true);
        }));

        return $promisor->promise();
    }

    private function getIdsFromIndex(string $indexName): Promise
    {
        return $this->redisClient->lRange(
            $indexName,
            0,
            -1
        );
    }

    public function addOneToMany(Identifiable $forModel, Identifiable $model): Promise
    {
        $indexName = $this->keyGenerator->getOneToManeIndexName($forModel, $model);

        return $this->addToIndex($indexName, $model->getId());
    }

    public function getIdsOneToMany(Identifiable $forModel, string $modelClassName): Promise
    {
        if (!is_subclass_of($modelClassName, Identifiable::class)) {
            return new Failure(new TypeError("{$modelClassName} not implemented ".Identifiable::class));
        }

        $indexName = $this->keyGenerator->getOneToManeIndexName($forModel, $modelClassName);

        return $this->getIdsFromIndex($indexName);
    }

    private function removeFromIndex(string $indexName, string $key): Promise
    {
        return $this->redisClient->lRem($indexName, $key);
    }

    public function removeOneToMany(Identifiable $forModel, Identifiable $model): Promise
    {
        $indexName = $this->keyGenerator->getOneToManeIndexName($forModel, $model);

        return $this->removeFromIndex($indexName, $model->getId());
    }

    private function moveValueOnIndex(string $indexName, string $targetId, string $positionId): Promise
    {
        $promisor = new Deferred();

        Loop::defer(wrap(function () use ($promisor, $targetId, $positionId, $indexName) {
            yield $this->redisClient->lRem($indexName, $targetId, 0);
            $insertResult = yield $this->redisClient->lInsert(
                $indexName,
                'before',
                $positionId,
                $targetId
            );

            if ($insertResult !== -1) {
                $promisor->resolve(true);
            } else {
                yield $this->redisClient->lPush($indexName, $targetId);

                $promisor->resolve(false);
            }
        }));

        return $promisor->promise();
    }

    public function moveValueOnOneToMany(
        Identifiable $forModel,
        Identifiable $model,
        Identifiable $positionModel
    ): Promise {
        $indexName = $this->keyGenerator->getOneToManeIndexName($forModel, $model);

        return $this->moveValueOnIndex($indexName,
            $model->getId(),
            $positionModel->getId()
        );
    }

    public function addManyToMany(Identifiable $modelFirst, Identifiable $modelSecond): Promise
    {
        $promisor = new Deferred();

        Loop::defer(wrap(function () use ($promisor, $modelFirst, $modelSecond) {
            yield $this->addOneToMany($modelFirst, $modelSecond);
            yield $this->addOneToMany($modelSecond, $modelFirst);

            $promisor->resolve(true);
        }));

        return $promisor->promise();
    }

    public function getIdsManyToMany(Identifiable $forModel, string $targetClassName): Promise
    {
        return $this->getIdsOneToMany($forModel, $targetClassName);
    }

    public function removeManyToMany(Identifiable $modelFirst, Identifiable $modelSecond): Promise
    {
        $promisor = new Deferred();

        Loop::defer(wrap(function () use ($promisor, $modelFirst, $modelSecond) {
            yield $this->redisClient->lRem($this->keyGenerator->getOneToManeIndexName($modelFirst, $modelSecond), $modelSecond->getId());
            yield $this->redisClient->lRem($this->keyGenerator->getOneToManeIndexName($modelSecond, $modelFirst), $modelFirst->getId());

            $promisor->resolve(true);
        }));

        return $promisor->promise();
    }
}
