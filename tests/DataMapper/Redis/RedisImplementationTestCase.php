<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Redis;

use Amp\Loop\LoopFactory;
use AsyncInterop\Loop;
use PHPUnit\Framework\TestCase;

abstract class RedisImplementationTestCase extends TestCase
{
    public static function setUpBeforeClass()
    {
        Loop::setFactory(new LoopFactory);
    }
}
