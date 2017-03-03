<?php declare(strict_types=1);

namespace Fedot\DataMapper;

use AsyncInterop\Promise;

interface RelationshipManagerInterface
{
    public function addOneToMany(Identifiable $forModel, Identifiable $model): Promise;

    public function getIdsOneToMany(Identifiable $forModel, string $modelClassName): Promise;

    public function removeOneToMany(Identifiable $forModel, Identifiable $model): Promise;

    public function moveValueOnOneToMany(
        Identifiable $forModel,
        Identifiable $model,
        Identifiable $positionModel
    ): Promise;

    public function addManyToMany(Identifiable $modelFirst, Identifiable $modelSecond): Promise;

    public function getIdsManyToMany(Identifiable $forModel, string $targetClassName): Promise;

    public function removeManyToMany(Identifiable $modelFirst, Identifiable $modelSecond): Promise;
}
