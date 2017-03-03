<?php declare(strict_types = 1);
namespace Tests\Fedot\DataMapper\Redis;

use Amp\Redis\Client;
use Amp\Success;
use Fedot\DataMapper\Identifiable as IdentifiableInterface;
use Fedot\DataMapper\Redis\KeyGenerator;
use Fedot\DataMapper\Redis\RelationshipManager;
use PHPUnit_Framework_MockObject_MockObject;
use Tests\Fedot\DataMapper\Stubs\AnotherIdentifiable;
use Tests\Fedot\DataMapper\Stubs\Identifiable;
use Tests\Fedot\DataMapper\Stubs\NotIdentifiable;
use TypeError;

class RelationshipManagerTest extends RedisImplementationTestCase
{
    /**
     * @var Client|PHPUnit_Framework_MockObject_MockObject
     */
    private $redisClientMock;

    private function getInstance(): RelationshipManager
    {
        $this->redisClientMock = $this->createMock(Client::class);

        return new RelationshipManager(new KeyGenerator(), $this->redisClientMock);
    }

    public function testAddOneToMany()
    {
        $instance = $this->getInstance();

        $forModel = new Identifiable('for-id');
        $model = new Identifiable('test-id');

        $this->redisClientMock->expects($this->once())
            ->method('lRem')
            ->with('index:tests_fedot_datamapper_stubs_identifiable:for-id:tests_fedot_datamapper_stubs_identifiable', 'test-id', 0)
            ->willReturn(new Success(1))
        ;

        $this->redisClientMock->expects($this->once())
            ->method('lPush')
            ->with('index:tests_fedot_datamapper_stubs_identifiable:for-id:tests_fedot_datamapper_stubs_identifiable', 'test-id')
            ->willReturn(new Success(1))
        ;

        $result = \Amp\wait($instance->addOneToMany($forModel, $model));

        $this->assertTrue($result);
    }

    public function testGetIdsOneToMany()
    {
        $instance = $this->getInstance();

        $forModel = new Identifiable('for-id');
        $modelClassName = Identifiable::class;

        $this->redisClientMock->expects($this->once())
            ->method('lRange')
            ->with('index:tests_fedot_datamapper_stubs_identifiable:for-id:tests_fedot_datamapper_stubs_identifiable', 0, -1)
            ->willReturn(new Success([
                'test-id1',
                'test-id2',
                'test-id4',
            ]))
        ;

        $result = \Amp\wait($instance->getIdsOneToMany($forModel, $modelClassName));

        $this->assertSame([
            'test-id1',
            'test-id2',
            'test-id4',
        ], $result);
    }

    public function testGetIdsOneToManyEmpty()
    {
        $instance = $this->getInstance();

        $forModel = new Identifiable('for-id');
        $modelClassName = Identifiable::class;

        $this->redisClientMock->expects($this->once())
            ->method('lRange')
            ->with('index:tests_fedot_datamapper_stubs_identifiable:for-id:tests_fedot_datamapper_stubs_identifiable', 0, -1)
            ->willReturn(new Success([]))
        ;

        $result = \Amp\wait($instance->getIdsOneToMany($forModel, $modelClassName));

        $this->assertSame([], $result);
    }

    public function testGetIdsOneToManyNotIdentifiable()
    {
        $instance = $this->getInstance();

        $forModel = new Identifiable('for-id');
        $className = NotIdentifiable::class;

        $this->redisClientMock->expects($this->never())
            ->method($this->anything())
        ;

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage("{$className} not implemented " . IdentifiableInterface::class);

        \Amp\wait($instance->getIdsOneToMany($forModel, $className));
    }

    public function testRemoveOneToMany()
    {
        $instance = $this->getInstance();

        $forModel = new Identifiable('for-id');
        $model = new Identifiable('test-id');

        $this->redisClientMock->expects($this->once())
            ->method('lRem')
            ->with('index:tests_fedot_datamapper_stubs_identifiable:for-id:tests_fedot_datamapper_stubs_identifiable', 'test-id')
            ->willReturn(new Success(1))
        ;

        $result = \Amp\wait($instance->removeOneToMany($forModel, $model));

        $this->assertSame(1, $result);
    }

    public function testMoveValueOnOneToManyPositive()
    {
        $instance = $this->getInstance();

        $forModel = new Identifiable('for-id');
        $model = new Identifiable('test-id');
        $position = new Identifiable('position-id');

        $indexName = 'index:tests_fedot_datamapper_stubs_identifiable:for-id:tests_fedot_datamapper_stubs_identifiable';

        $this->redisClientMock->expects($this->once())
            ->method('lRem')
            ->with($indexName, 'test-id', 0)
            ->willReturn(new Success(1))
        ;
        $this->redisClientMock->expects($this->once())
            ->method('lInsert')
            ->with($indexName, 'before', 'position-id', 'test-id')
            ->willReturn(new Success(1))
        ;

        $result = \Amp\wait($instance->moveValueOnOneToMany($forModel, $model, $position));

        $this->assertTrue($result);
    }

    public function testMoveValueOnOneToManyNegative()
    {
        $instance = $this->getInstance();

        $forModel = new Identifiable('for-id');
        $model = new Identifiable('test-id');
        $position = new Identifiable('position-id');

        $indexName = 'index:tests_fedot_datamapper_stubs_identifiable:for-id:tests_fedot_datamapper_stubs_identifiable';

        $this->redisClientMock->expects($this->once())
            ->method('lRem')
            ->with($indexName, 'test-id', 0)
            ->willReturn(new Success(1))
        ;
        $this->redisClientMock->expects($this->once())
            ->method('lInsert')
            ->with($indexName, 'before', 'position-id', 'test-id')
            ->willReturn(new Success(-1))
        ;
        $this->redisClientMock->expects($this->once())
            ->method('lPush')
            ->with($indexName, 'test-id')
            ->willReturn(new Success(1))
        ;

        $result = \Amp\wait($instance->moveValueOnOneToMany($forModel, $model, $position));

        $this->assertFalse($result);
    }

    public function testAddManyToMany()
    {
        $instance = $this->getInstance();

        $entity1 = new Identifiable('entity-1');
        $entity2 = new AnotherIdentifiable('entity-2');

        $indexName1 = "index:tests_fedot_datamapper_stubs_identifiable:entity-1:tests_fedot_datamapper_stubs_anotheridentifiable";
        $indexName2 = "index:tests_fedot_datamapper_stubs_anotheridentifiable:entity-2:tests_fedot_datamapper_stubs_identifiable";

        $this->redisClientMock->expects($this->exactly(2))
            ->method('lRem')
            ->withConsecutive(
                [$indexName1, 'entity-2', 0],
                [$indexName2, 'entity-1', 0]
            )
            ->willReturnOnConsecutiveCalls(
                new Success(1),
                new Success(1)
            )
        ;

        $this->redisClientMock->expects($this->exactly(2))
            ->method('lPush')
            ->withConsecutive(
                [$indexName1, 'entity-2'],
                [$indexName2, 'entity-1']
            )
            ->willReturnOnConsecutiveCalls(
                new Success(true),
                new Success(true)
            )
        ;

        $result = \Amp\wait($instance->addManyToMany($entity1, $entity2));

        $this->assertTrue($result);
    }

    public function testGetIdsManyToMany()
    {
        $instance = $this->getInstance();

        $forModel = new Identifiable('for-id');
        $modelClassName = Identifiable::class;

        $this->redisClientMock->expects($this->once())
            ->method('lRange')
            ->with('index:tests_fedot_datamapper_stubs_identifiable:for-id:tests_fedot_datamapper_stubs_identifiable', 0, -1)
            ->willReturn(new Success([
                'test-id1',
                'test-id2',
                'test-id4',
            ]))
        ;

        $result = \Amp\wait($instance->getIdsManyToMany($forModel, $modelClassName));

        $this->assertSame([
            'test-id1',
            'test-id2',
            'test-id4',
        ], $result);
    }

    public function testRemoveManyToMany()
    {
        $instance = $this->getInstance();

        $entity1 = new Identifiable('entity-1');
        $entity2 = new AnotherIdentifiable('entity-2');

        $indexName1 = "index:tests_fedot_datamapper_stubs_identifiable:entity-1:tests_fedot_datamapper_stubs_anotheridentifiable";
        $indexName2 = "index:tests_fedot_datamapper_stubs_anotheridentifiable:entity-2:tests_fedot_datamapper_stubs_identifiable";

        $this->redisClientMock->expects($this->exactly(2))
            ->method('lRem')
            ->withConsecutive(
                [$indexName1, 'entity-2'],
                [$indexName2, 'entity-1']
            )
            ->willReturnOnConsecutiveCalls(
                new Success(true),
                new Success(true)
            )
        ;

        $result = \Amp\wait($instance->removeManyToMany($entity1, $entity2));

        $this->assertTrue($result);
    }
}
