<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Redis;

use Amp\Process\Process;
use Amp\Redis\Client;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Instantiator\Instantiator;
use Fedot\DataMapper\Metadata\Driver\AnnotationDriver;
use Fedot\DataMapper\ModelManagerInterface;
use Fedot\DataMapper\Redis\ModelManager;
use Metadata\MetadataFactory;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use function Amp\call;
use function Amp\Promise\wait;

class RedisImplementationIntegrationTest extends RedisImplementationTestCase
{
    /**
     * @var Process
     */
    private static $dbProcess;

    /**
     * @var Client
     */
    private $redisClient;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$dbProcess = new Process(
            'redis-server --daemonize no --port 25325 --timeout 333 --pidfile /tmp/amp-redis.pid'
        );

        wait(call(function (Process $dbProcess) {
            $dbProcess->start();

            $stream = $dbProcess->getStdout();
            $output = '';

            while ($chunk = yield $stream->read()) {
                $output .= $chunk;

                if (strstr($output, 'Ready to accept connections')) {
                    break;
                }
            }
        }, self::$dbProcess));
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
