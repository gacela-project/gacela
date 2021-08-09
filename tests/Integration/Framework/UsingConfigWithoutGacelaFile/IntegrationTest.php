<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigWithoutGacelaFile;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::init(__DIR__);
    }

    public function test_load_default_config(): void
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
