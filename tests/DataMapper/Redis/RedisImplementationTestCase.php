<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Redis;

use Amp\Loop;
use PHPUnit\Framework\TestCase;

abstract class RedisImplementationTestCase extends TestCase
{
    public static function setUpBeforeClass()
    {
        // Reset Loop
        Loop::set((new Loop\DriverFactory())->create());
    }
}
