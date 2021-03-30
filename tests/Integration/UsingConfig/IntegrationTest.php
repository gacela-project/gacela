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
        Config::init();
    }

    public function testRemoveKeyFromContainer(): void
    {
        $facade = new LocalConfig\Facade();
        self::assertSame(
            [
                'config' => 1,
                'config_local' => 2,
                'override' => 5,
            ],
            $facade->doSomething()
        );
    }
}
