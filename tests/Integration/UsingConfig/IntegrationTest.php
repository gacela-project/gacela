<?php

declare(strict_types=1);

namespace GacelaTest\Integration\UsingConfig;

use Gacela\Config;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        Config::setApplicationRootDir(__DIR__);
    }

    public function testRemoveKeyFromContainer(): void
    {
        $facade = new SimpleModule\Facade();
        self::assertSame(1, $facade->doSomething());
    }
}
