<?php declare(strict_types=1);

namespace Fedot\DataMapper\Metadata\Driver;

use Doctrine\Common\Annotations\Reader;
use Fedot\DataMapper\Annotation\Field;
use Fedot\DataMapper\Annotation\Id;
use Fedot\DataMapper\Annotation\ReferenceMany;
use Fedot\DataMapper\Annotation\ReferenceOne;
use Fedot\DataMapper\Metadata\ClassMetadata;
use Fedot\DataMapper\Metadata\PropertyMetadata;
use Metadata\Driver\DriverInterface;
use ReflectionClass;

class AnnotationDriver implements DriverInterface
{
    /**
     * @var Reader
     */
    private $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function loadMetadataForClass(ReflectionClass $class)
    {
        $classMetadata = new ClassMetadata($name = $class->name);
        $classMetadata->fileResources[] = $class->getFileName();

        foreach ($class->getProperties() as $property) {
            $propertyMetadata = new PropertyMetadata($class->getName(), $property->getName());

            foreach ($this->reader->getPropertyAnnotations($property) as $propertyAnnotation) {
                if ($propertyAnnotation instanceof Id) {
                    $propertyMetadata->isId = true;
                    $propertyMetadata->isField = true;
                    $propertyMetadata->type = $propertyAnnotation->type;

                    $classMetadata->idField = $property->getName();
                } elseif ($propertyAnnotation instanceof Field) {
                    $propertyMetadata->isField = true;
                    $propertyMetadata->type = $propertyAnnotation->type;
                } elseif ($propertyAnnotation instanceof ReferenceOne) {
                    $propertyMetadata->referenceType = 'one';
                    $propertyMetadata->referenceTarget = $propertyAnnotation->target;
                } elseif ($propertyAnnotation instanceof ReferenceMany) {
                    $propertyMetadata->referenceType = 'many';
                    $propertyMetadata->referenceTarget = $propertyAnnotation->target;
                }
            }

            $classMetadata->addPropertyMetadata($propertyMetadata);
        }

        return $classMetadata;
    }
}
