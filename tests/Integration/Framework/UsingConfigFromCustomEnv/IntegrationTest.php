<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigFromCustomEnv;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function tearDown(): void
    {
        # Remove the APPLICATION_ENV
        putenv('APPLICATION_ENV');
    }

    public function test_load_config_from_custom_env_dev(): void
    {
        putenv('APPLICATION_ENV=dev');
        Gacela::bootstrap(__DIR__);
        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'config-php' => 2,
                'override' => 4,
            ],
            $facade->doSomething()
        );
    }

    public function test_load_config_from_custom_env_prod(): void
    {
        putenv('APPLICATION_ENV=prod');
        Gacela::bootstrap(__DIR__);
        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'config-php' => 3,
                'override' => 4,
            ],
            $facade->doSomething()
        );
    }
}
