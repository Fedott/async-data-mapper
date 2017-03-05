<?php declare(strict_types=1);

namespace Fedot\DataMapper;

use AsyncInterop\Promise;

interface ModelManagerInterface
{
    public function persist($model): Promise;

    public function find(string $class, string $id): Promise;
}
