<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Redis;

use Amp\Loop;
use PHPUnit\Framework\TestCase;
use Tests\Fedot\DataMapper\AbstractModelManagerTestCase;

abstract class RedisImplementationTestCase extends AbstractModelManagerTestCase
{
    public static function setUpBeforeClass()
    {
        // Reset Loop
        Loop::set(new Loop\NativeDriver());
    }
}
