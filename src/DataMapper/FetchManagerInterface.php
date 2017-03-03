<?php declare(strict_types=1);

namespace Fedot\DataMapper;

use AsyncInterop\Promise;

interface FetchManagerInterface
{
    public function fetchById(string $className, string $id): Promise;

    public function fetchCollectionByIds(string $className, array $ids): Promise;
}
