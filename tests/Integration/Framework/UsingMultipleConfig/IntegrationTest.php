<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingMultipleConfig;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::init(__DIR__);
    }

    public function test_remove_key_from_container(): void
    {
        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'config-env' => 1,
                'config-php' => 3,
                'override' => 4,
            ],
            $facade->doSomething()
        );
    }
}
