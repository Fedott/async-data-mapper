<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Redis;

use Amp\Redis\Client;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Instantiator\Instantiator;
use Fedot\DataMapper\IdentityMap;
use Fedot\DataMapper\Metadata\Driver\AnnotationDriver;
use Fedot\DataMapper\ModelManagerInterface;
use Fedot\DataMapper\Redis\ModelManager;
use Metadata\MetadataFactory;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Tests\Fedot\DataMapper\Stubs\Integration\Author;
use Tests\Fedot\DataMapper\Stubs\Integration\AuthorBio;
use Tests\Fedot\DataMapper\Stubs\Integration\Book;
use Tests\Fedot\DataMapper\Stubs\Integration\Genre;
use Tests\Fedot\DataMapper\Stubs\Integration\SimpleModel;
use function Amp\Promise\all;
use function Amp\Promise\wait;

class RedisImplementationIntegrationTest extends RedisImplementationTestCase
{
    /**
     * @var Client
     */
    private $redisClient;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        print `redis-server --daemonize yes --port 25325 --timeout 333 --pidfile /tmp/amp-redis.pid`;
//        sleep(1);
    }

    public static function tearDownAfterClass()
    {
        $pid = @file_get_contents('/tmp/amp-redis.pid');
        @unlink('/tmp/amp-redis.pid');

        if (!empty($pid)) {
            print `kill $pid`;
        }
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->redisClient->close();
    }

    protected function getModelManager(): ModelManagerInterface
    {
        $this->redisClient = new Client('tcp://localhost:25325?database=7');

        $propertyAccessor = new PropertyAccessor();
        $modelManager = new ModelManager(
            new MetadataFactory(new AnnotationDriver(new AnnotationReader())), $this->redisClient,
            $propertyAccessor,
            new Instantiator()
        );

        return $modelManager;
    }
}
