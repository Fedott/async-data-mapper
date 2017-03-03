<?php declare(strict_types = 1);
namespace Tests\Fedot\DataMapper\Stubs;

use Fedot\DataMapper\Identifiable as IdentifiableInterface;

class AnotherIdentifiable implements IdentifiableInterface
{
    public $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
