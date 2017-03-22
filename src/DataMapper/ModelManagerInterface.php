<?php declare(strict_types=1);

namespace Fedot\DataMapper;

use AsyncInterop\Promise;

interface ModelManagerInterface
{
    public function persist($model, IdentityMap $identityMap = null): Promise;

    public function remove($model, IdentityMap $identityMap = null): Promise;

    public function find(string $class, string $id, int $depthLevel = 1, IdentityMap $identityMap = null): Promise;
}
