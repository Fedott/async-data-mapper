<?php declare(strict_types=1);

namespace Fedot\DataMapper;

use Fedot\DataMapper\Metadata\ClassMetadata;

class IdentityMap
{
    private $identityMap = [];

    public function has(ClassMetadata $metadata, string $id)
    {
        return array_key_exists($metadata->name, $this->identityMap)
            && array_key_exists($id, $this->identityMap[$metadata->name]);
    }

    public function get(ClassMetadata $metadata, string $id)
    {
        return $this->identityMap[$metadata->name][$id] ?? null;
    }

    public function add(ClassMetadata $metadata, string $id, $model)
    {
        $this->identityMap[$metadata->name][$id] = $model;
    }

    public function clear()
    {
        $this->identityMap = [];
    }
}
