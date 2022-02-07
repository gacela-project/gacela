<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigFromCustomEnv;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        putenv('APPLICATION_ENV=dev');
        Gacela::bootstrap(__DIR__);
    }

    public function tearDown(): void
    {
        putenv('APPLICATION_ENV');
    }

    public function test_load_config_from_custom_env_dev(): void
    {
        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'config-php' => 2,
                'override' => 2,
            ],
            $facade->doSomething()
        );
    }
}
