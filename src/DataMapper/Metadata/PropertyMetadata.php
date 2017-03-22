<?php declare(strict_types=1);

namespace Fedot\DataMapper\Metadata;

use Metadata\PropertyMetadata as BasePropertyMetadata;

class PropertyMetadata extends BasePropertyMetadata
{
    /**
     * @var bool
     */
    public $isField = false;

    /**
     * @var bool
     */
    public $isId = false;

    /**
     * @var string|null
     */
    public $type;

    /**
     * @var string|null
     */
    public $referenceType;

    /**
     * @var string|null
     */
    public $referenceTarget;
}
