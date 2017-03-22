<?php declare(strict_types=1);

namespace Fedot\DataMapper\Annotation;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class ReferenceOne
{
    /**
     * @var string
     *
     * @Required
     */
    public $target;
}
