<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Metadata\Driver\Fixtures;

use Fedot\DataMapper\Annotation as Mapping;

class FirstModel
{
    /**
     * @var string
     *
     * @Mapping\Id
     */
    private $id;

    /**
     * @var string
     *
     * @Mapping\Field
     */
    private $field1;

    /**
     * @var string
     */
    private $field2;

    /**
     * @var AnotherModel
     *
     * @Mapping\ReferenceOne(target=AnotherModel::class)
     */
    private $referenceOneField;

    /**
     * @var SecondModel[]
     *
     * @Mapping\ReferenceMany(target=SecondModel::class)
     */
    private $referenceManyField;

    public function __construct(
        string $id,
        string $field1,
        AnotherModel $referenceOneField = null,
        array $referenceManyField = []
    ) {
        $this->id                 = $id;
        $this->field1             = $field1;
        $this->referenceOneField  = $referenceOneField;
        $this->referenceManyField = $referenceManyField;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getField1(): string
    {
        return $this->field1;
    }

    public function getField2(): ?string
    {
        return $this->field2;
    }

    public function setField2(string $field2)
    {
        $this->field2 = $field2;
    }

    public function getReferenceOneField(): ?AnotherModel
    {
        return $this->referenceOneField;
    }

    public function getReferenceManyField(): array
    {
        return $this->referenceManyField;
    }
}
