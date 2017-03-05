<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Stubs\Integration;

use Fedot\DataMapper\Annotation as Mapping;

class SimpleModel
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

    public function __construct(string $id, string $field1)
    {
        $this->id     = $id;
        $this->field1 = $field1;
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
}
