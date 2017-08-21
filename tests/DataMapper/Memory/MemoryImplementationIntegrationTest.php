<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Memory;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Instantiator\Instantiator;
use Fedot\DataMapper\Memory\ModelManager;
use Fedot\DataMapper\Metadata\Driver\AnnotationDriver;
use Fedot\DataMapper\ModelManagerInterface;
use Metadata\MetadataFactory;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Tests\Fedot\DataMapper\AbstractModelManagerTestCase;

class MemoryImplementationIntegrationTest extends AbstractModelManagerTestCase
{
    protected function getModelManager(): ModelManagerInterface
    {
        $propertyAccessor = new PropertyAccessor();
        $modelManager = new ModelManager(
            new MetadataFactory(
                new AnnotationDriver(
                    new AnnotationReader()
                )
            ),
            $propertyAccessor,
            new Instantiator(),
            2
        );

        return $modelManager;
    }
}
