<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Mongo;

use Amp\Loop;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Instantiator\Instantiator;
use Fedot\DataMapper\Metadata\Driver\AnnotationDriver;
use Fedot\DataMapper\ModelManagerInterface;
use Fedot\DataMapper\Mongo\ModelManager;
use Metadata\MetadataFactory;
use MongoDB\Client;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Tests\Fedot\DataMapper\AbstractModelManagerTestCase;

class MongoImplementationIntegrationTest extends AbstractModelManagerTestCase
{
    /**
     * @var Client
     */
    private $mongoClient;

    private static $mongoProcessResource;

    public static function setUpBeforeClass()
    {
        Loop::set(new Loop\NativeDriver());

        @mkdir('/tmp/amp-mongo-data', 0777, true);

        static::$mongoProcessResource = proc_open(
            'mongod --port 23456 --pidfilepath /tmp/amp-mongo.pid --dbpath /tmp/amp-mongo-data --syncdelay 0',
            [
                0 => array("pipe", "r"),
                1 => array("pipe", "w"),
            ],
            $pipes
        );
        sleep(1);
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
