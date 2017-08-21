<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Metadata\Driver;

use Doctrine\Common\Annotations\AnnotationReader;
use Fedot\DataMapper\Metadata\ClassMetadata;
use Fedot\DataMapper\Metadata\Driver\AnnotationDriver;
use Fedot\DataMapper\Metadata\PropertyMetadata;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Tests\Fedot\DataMapper\Metadata\Driver\Fixtures\AnotherModel;
use Tests\Fedot\DataMapper\Metadata\Driver\Fixtures\FirstModel;
use Tests\Fedot\DataMapper\Metadata\Driver\Fixtures\SecondModel;

class AnnotationDriverTest extends TestCase
{
    public function testAnnotationDriver()
    {
        $metadataFactory = new MetadataFactory(
            new AnnotationDriver(new AnnotationReader())
        );

        /** @var ClassMetadata $metadata */
        $metadata = $metadataFactory->getMetadataForClass(FirstModel::class);

        $this->assertInstanceOf(ClassMetadata::class, $metadata);

        $this->assertEquals('id', $metadata->idField);

        $this->assertCount(5, $metadata->propertyMetadata);

        /** @var PropertyMetadata $idPropertyMetadata */
        /** @var PropertyMetadata $field1PropertyMetadata */
        /** @var PropertyMetadata $field2PropertyMetadata */
        ['id'     => $idPropertyMetadata,
         'field1' => $field1PropertyMetadata,
         'field2' => $field2PropertyMetadata,
         'referenceOneField' => $referenceOneFieldMetadata,
         'referenceManyField' => $referenceManyFieldMetadata,
        ] = $metadata->propertyMetadata;

        $this->assertInstanceOf(PropertyMetadata::class, $idPropertyMetadata);
        $this->assertEquals(true, $idPropertyMetadata->isId);
        $this->assertEquals(true, $idPropertyMetadata->isField);
        $this->assertEquals('string', $idPropertyMetadata->type);
        $this->assertEquals(null, $idPropertyMetadata->referenceType);
        $this->assertEquals(null, $idPropertyMetadata->referenceTarget);

        $this->assertInstanceOf(PropertyMetadata::class, $field1PropertyMetadata);
        $this->assertEquals(false, $field1PropertyMetadata->isId);
        $this->assertEquals(true, $field1PropertyMetadata->isField);
        $this->assertEquals('string', $field1PropertyMetadata->type);
        $this->assertEquals(null, $field1PropertyMetadata->referenceType);
        $this->assertEquals(null, $field1PropertyMetadata->referenceTarget);

        $this->assertInstanceOf(PropertyMetadata::class, $field2PropertyMetadata);
        $this->assertEquals(false, $field2PropertyMetadata->isId);
        $this->assertEquals(false, $field2PropertyMetadata->isField);
        $this->assertEquals(null, $field2PropertyMetadata->type);
        $this->assertEquals(null, $field2PropertyMetadata->referenceType);
        $this->assertEquals(null, $field2PropertyMetadata->referenceTarget);

        $this->assertInstanceOf(PropertyMetadata::class, $referenceOneFieldMetadata);
        $this->assertEquals(false, $referenceOneFieldMetadata->isId);
        $this->assertEquals(false, $referenceOneFieldMetadata->isField);
        $this->assertEquals(null, $referenceOneFieldMetadata->type);
        $this->assertEquals('one', $referenceOneFieldMetadata->referenceType);
        $this->assertEquals(AnotherModel::class, $referenceOneFieldMetadata->referenceTarget);

        $this->assertInstanceOf(PropertyMetadata::class, $referenceManyFieldMetadata);
        $this->assertEquals(false, $referenceManyFieldMetadata->isId);
        $this->assertEquals(false, $referenceManyFieldMetadata->isField);
        $this->assertEquals(null, $referenceManyFieldMetadata->type);
        $this->assertEquals('many', $referenceManyFieldMetadata->referenceType);
        $this->assertEquals(SecondModel::class, $referenceManyFieldMetadata->referenceTarget);
    }
}
