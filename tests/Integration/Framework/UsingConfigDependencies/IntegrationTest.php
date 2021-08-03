<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigDependencies;

use Gacela\Framework\Config;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        Config::getInstance()->init(__DIR__);
    }

    public function test_remove_key_from_container(): void
    {
        $facade = new LocalConfig\Facade();

        self::assertSame(
            'Hello 100!',
            $facade->generateNumberString()
        );
    }
}
