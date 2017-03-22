<?php declare(strict_types=1);

namespace Fedot\DataMapper\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Id extends Field
{
}
