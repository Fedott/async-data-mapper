<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Mongo;

use Amp\Loop;
use Amp\Process\Process;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Instantiator\Instantiator;
use Fedot\DataMapper\Metadata\Driver\AnnotationDriver;
use Fedot\DataMapper\ModelManagerInterface;
use Fedot\DataMapper\Mongo\ModelManager;
use Metadata\MetadataFactory;
use MongoDB\Client;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Tests\Fedot\DataMapper\AbstractModelManagerTestCase;
use function Amp\call;
use function Amp\Promise\wait;

class MongoImplementationIntegrationTest extends AbstractModelManagerTestCase
{
    /**
     * @var Client
     */
    private $mongoClient;

    /**
     * @var Process
     */
    private static $mongoProcess;

    public static function setUpBeforeClass()
    {
        Loop::set(new Loop\NativeDriver());

        @mkdir('/tmp/amp-mongo-data', 0777, true);

        self::$mongoProcess = new Process(
            'mongod --port 23456 --pidfilepath /tmp/amp-mongo.pid --dbpath /tmp/amp-mongo-data --syncdelay 0'
        );

        wait(call(function (Process $mongoProcess) {
            $mongoProcess->start();

            $stream = $mongoProcess->getStdout();
            $output = '';

            while ($chunk = yield $stream->read()) {
                $output .= $chunk;

                if (strstr($output, 'waiting for connections on port')) {
                    break;
                }
            }
        }, self::$mongoProcess));
    }

    public static function tearDownAfterClass()
    {
        $pid = @file_get_contents('/tmp/amp-mongo.pid');
        @unlink('/tmp/amp-mongo.pid');

        if (!empty($pid)) {
            print `kill $pid`;
        }
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->mongoClient = null;
    }

    protected function getModelManager(): ModelManagerInterface
    {
        $this->mongoClient = new Client('mongodb://localhost:23456');

        $propertyAccessor = new PropertyAccessor();
        $modelManager = new ModelManager(
            new MetadataFactory(
                new AnnotationDriver(
                    new AnnotationReader()
                )
            ),
            $this->mongoClient->selectDatabase('testDb'),
            $propertyAccessor,
            new Instantiator()
        );

        return $modelManager;
    }
}
