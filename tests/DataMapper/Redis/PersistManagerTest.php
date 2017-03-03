<?php declare(strict_types = 1);
namespace Tests\Fedot\DataMapper\Redis;

use Amp\Redis\Client;
use Amp\Success;
use Fedot\DataMapper\Redis\KeyGenerator;
use Fedot\DataMapper\Redis\PersistManager;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\Serializer\SerializerInterface;
use Tests\Fedot\DataMapper\Stubs\Identifiable;

class PersistManagerTest extends RedisImplementationTestCase
{
    /**
     * @var Client|PHPUnit_Framework_MockObject_MockObject
     */
    private $redisClientMock;

    /**
     * @var SerializerInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $serializerMock;

    public function getInstance(): PersistManager
    {
        $this->redisClientMock = $this->createMock(Client::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);

        return new PersistManager(
            new KeyGenerator(),
            $this->redisClientMock,
            $this->serializerMock
        );
    }

    public function testPersistNotUpdate()
    {
        $instance = $this->getInstance();

        $model = new Identifiable('test-id');

        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->with($model, 'json')
            ->willReturn('test-json')
        ;

        $this->redisClientMock
            ->expects($this->once())
            ->method('setNx')
            ->with('entity:tests_fedot_datamapper_stubs_identifiable:test-id', 'test-json')
            ->willReturn(new Success(true))
        ;

        $result = \Amp\wait($instance->persist($model, false));

        $this->assertTrue($result);
    }

    public function testPersistNotUpdateNegative()
    {
        $instance = $this->getInstance();

        $model = new Identifiable('test-id');

        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->with($model, 'json')
            ->willReturn('test-json')
        ;

        $this->redisClientMock
            ->expects($this->once())
            ->method('setNx')
            ->with('entity:tests_fedot_datamapper_stubs_identifiable:test-id', 'test-json')
            ->willReturn(new Success(false))
        ;

        $result = \Amp\wait($instance->persist($model, false));

        $this->assertFalse($result);
    }

    public function testPersistUpdate()
    {
        $instance = $this->getInstance();

        $model = new Identifiable('test-id');

        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->with($model, 'json')
            ->willReturn('test-json')
        ;

        $this->redisClientMock
            ->expects($this->once())
            ->method('set')
            ->with('entity:tests_fedot_datamapper_stubs_identifiable:test-id', 'test-json')
            ->willReturn(new Success(true))
        ;

        $result = \Amp\wait($instance->persist($model, true));

        $this->assertTrue($result);
    }

    public function testPersistUpdateNegative()
    {
        $instance = $this->getInstance();

        $model = new Identifiable('test-id');

        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->with($model, 'json')
            ->willReturn('test-json')
        ;

        $this->redisClientMock
            ->expects($this->once())
            ->method('set')
            ->with('entity:tests_fedot_datamapper_stubs_identifiable:test-id', 'test-json')
            ->willReturn(new Success(false))
        ;

        $result = \Amp\wait($instance->persist($model, true));

        $this->assertFalse($result);
    }

    public function testRemove()
    {
        $instance = $this->getInstance();

        $model = new Identifiable('test-id');

        $this->redisClientMock->expects($this->once())
            ->method('del')
            ->with('entity:tests_fedot_datamapper_stubs_identifiable:test-id')
            ->willReturn(new Success(1))
        ;

        $result = \Amp\wait($instance->remove($model));

        $this->assertSame(1, $result);
    }
}
