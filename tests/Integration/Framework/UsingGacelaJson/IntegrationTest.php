<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingGacelaJson;

use Gacela\Framework\Config;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function test_remove_key_from_container(): void
    {
        $this->expectDeprecation();
        Config::getInstance()->init(__DIR__);
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
