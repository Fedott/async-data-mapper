<?php declare(strict_types=1);

namespace Fedot\DataMapper\Annotation;

use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Field
{
    /**
     * @var string
     * @Enum({"string"})
     */
    public $type = 'string';
}
