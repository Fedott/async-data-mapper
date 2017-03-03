<?php declare(strict_types=1);

namespace Fedot\DataMapper;

use AsyncInterop\Promise;

interface PersistManagerInterface
{
    public function persist(Identifiable $identifiable, bool $update = false): Promise;

    public function remove(Identifiable $identifiable): Promise;
}
