<?php declare(strict_types=1);
namespace Fedot\DataMapper\Redis;

use Fedot\DataMapper\Identifiable;

class KeyGenerator
{
    private function getRedisName($model): string
    {
        if (!is_string($model)) {
            $model = get_class($model);
        }

        return strtolower(str_replace('\\', '_', $model));
    }

    public function getKeyForIdentifiable(Identifiable $model): string
    {
        return $this->getKeyForClassNameId($this->getRedisName($model), $model->getId());
    }

    public function getKeyForClassNameId(string $className, string $id): string
    {
        return "entity:{$this->getRedisName($className)}:{$id}";
    }

    public function getOneToManeIndexName(Identifiable $forModel, $model): string
    {
        $forModelName = $this->getRedisName($forModel);
        $modelName = $this->getRedisName($model);

        return "index:{$forModelName}:{$forModel->getId()}:{$modelName}";
    }
}
