<?php declare(strict_types = 1);
namespace Tests\Fedot\DataMapper\Redis;

use Amp\Redis\Client;
use Amp\Success;
use Fedot\DataMapper\Identifiable as IdentifiableInterface;
use Fedot\DataMapper\Redis\FetchManager;
use Fedot\DataMapper\Redis\KeyGenerator;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\Serializer\SerializerInterface;
use Tests\Fedot\DataMapper\Stubs\Identifiable;
use Tests\Fedot\DataMapper\Stubs\NotIdentifiable;
use TypeError;

class FetchManagerTest extends RedisImplementationTestCase
{
    /**
     * @var Client|PHPUnit_Framework_MockObject_MockObject
     */
    private $redisClientMock;

    /**
     * @var SerializerInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $serializerMock;

    public function getInstance(): FetchManager
    {
        $this->redisClientMock = $this->createMock(Client::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);

        return new FetchManager(
            new KeyGenerator(),
            $this->redisClientMock,
            $this->serializerMock
        );
    }

    public function testFetchByIdFound()
    {
        $instance = $this->getInstance();

        $className = Identifiable::class;

        $this->redisClientMock
            ->expects($this->once())
            ->method('get')
            ->with('entity:tests_fedot_datamapper_stubs_identifiable:test-id')
            ->willReturn(new Success('saved-json'))
        ;

        $this->serializerMock
            ->expects($this->once())
            ->method('deserialize')
            ->with('saved-json', $className, 'json')
            ->willReturn(new Identifiable('test-id'))
        ;

        $actualResult = \Amp\wait($instance->fetchById($className, 'test-id'));

        $this->assertInstanceOf(Identifiable::class, $actualResult);
        $this->assertEquals('test-id', $actualResult->id);
    }

    public function testFetchByIdNotFound()
    {
        $instance = $this->getInstance();

        $className = Identifiable::class;

        $this->redisClientMock
            ->expects($this->once())
            ->method('get')
            ->with('entity:tests_fedot_datamapper_stubs_identifiable:test-id')
            ->willReturn(new Success(null))
        ;

        $this->serializerMock
            ->expects($this->never())
            ->method('deserialize')
        ;

        $actualResult = \Amp\wait($instance->fetchById($className, 'test-id'));

        $this->assertNull($actualResult);
    }

    public function testFetchByIdNotIdentifiable()
    {
        $instance = $this->getInstance();

        $className = NotIdentifiable::class;

        $this->redisClientMock
            ->expects($this->never())
            ->method('get')
        ;

        $this->serializerMock
            ->expects($this->never())
            ->method('deserialize')
        ;


        $this->expectException(TypeError::class);
        $this->expectExceptionMessage("{$className} not implemented " . IdentifiableInterface::class);
        \Amp\wait($instance->fetchById($className, 'test-id'));
    }

    public function testFetchCollectionByIdsFound()
    {
        $instance = $this->getInstance();

        $className = Identifiable::class;

        $this->redisClientMock
            ->expects($this->once())
            ->method('mGet')
            ->with([
                'entity:tests_fedot_datamapper_stubs_identifiable:test-id1',
                'entity:tests_fedot_datamapper_stubs_identifiable:test-id2',
                'entity:tests_fedot_datamapper_stubs_identifiable:test-id3',
                'entity:tests_fedot_datamapper_stubs_identifiable:test-id4',
            ])
            ->willReturn(new Success([
                'test-json1',
                'test-json2',
                'test-json4',
            ]))
        ;

        $this->serializerMock
            ->expects($this->exactly(3))
            ->method('deserialize')
            ->withConsecutive(
                ['test-json1', $className, 'json'],
                ['test-json2', $className, 'json'],
                ['test-json4', $className, 'json']
            )
            ->willReturnOnConsecutiveCalls(
                new Identifiable('test-id1'),
                new Identifiable('test-id2'),
                new Identifiable('test-id4')
            )
        ;

        $actualResult = \Amp\wait($instance->fetchCollectionByIds($className, [
            'test-id1',
            'test-id2',
            'test-id3',
            'test-id4',
        ]));

        $this->assertCount(3, $actualResult);
        $this->assertInstanceOf($className, $actualResult[0]);
        $this->assertEquals('test-id1', $actualResult[0]->id);
        $this->assertInstanceOf($className, $actualResult[1]);
        $this->assertEquals('test-id2', $actualResult[1]->id);
        $this->assertInstanceOf($className, $actualResult[2]);
        $this->assertEquals('test-id4', $actualResult[2]->id);
    }

    public function testFetchCollectionByIdsNotFound()
    {
        $instance = $this->getInstance();

        $className = Identifiable::class;

        $this->redisClientMock
            ->expects($this->once())
            ->method('mGet')
            ->with([
                'entity:tests_fedot_datamapper_stubs_identifiable:test-id1',
                'entity:tests_fedot_datamapper_stubs_identifiable:test-id2',
                'entity:tests_fedot_datamapper_stubs_identifiable:test-id3',
                'entity:tests_fedot_datamapper_stubs_identifiable:test-id4',
            ])
            ->willReturn(new Success([]))
        ;

        $this->serializerMock
            ->expects($this->never())
            ->method('deserialize')
        ;

        $actualResult = \Amp\wait($instance->fetchCollectionByIds($className, [
            'test-id1',
            'test-id2',
            'test-id3',
            'test-id4',
        ]));

        $this->assertCount(0, $actualResult);
    }

    public function testFetchCollectionByIdsEmptyIds()
    {
        $instance = $this->getInstance();

        $className = Identifiable::class;

        $this->redisClientMock
            ->expects($this->never())
            ->method('mGet')
        ;

        $this->serializerMock
            ->expects($this->never())
            ->method('deserialize')
        ;

        $actualResult = \Amp\wait($instance->fetchCollectionByIds($className, []));

        $this->assertCount(0, $actualResult);
    }

    public function testFetchCollectionByIdsNotIdentifiable()
    {
        $instance = $this->getInstance();

        $className = NotIdentifiable::class;

        $this->redisClientMock
            ->expects($this->never())
            ->method('mGet')
        ;

        $this->serializerMock
            ->expects($this->never())
            ->method('deserialize')
        ;

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage("{$className} not implemented " . IdentifiableInterface::class);

        \Amp\wait($instance->fetchCollectionByIds($className, []));
    }
}
