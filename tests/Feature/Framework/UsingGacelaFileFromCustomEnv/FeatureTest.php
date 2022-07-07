<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingGacelaFileFromCustomEnv;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function tearDown(): void
    {
        # Remove the APP_ENV
        putenv('APP_ENV');
    }

    public function test_load_config_from_custom_env_default(): void
    {
        Gacela::bootstrap(__DIR__);

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'key' => 'from:default',
            ],
            $facade->doSomething()
        );
    }

    public function test_load_config_from_custom_env_dev(): void
    {
        self::markTestSkipped('TODO');
        putenv('APP_ENV=dev');

        Gacela::bootstrap(__DIR__);

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'key' => 'from:dev',
            ],
            $facade->doSomething()
        );
    }

    public function test_load_config_from_custom_env_prod(): void
    {
        self::markTestSkipped('TODO');
        putenv('APP_ENV=prod');

        Gacela::bootstrap(__DIR__);

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'key' => 'from:prod',
            ],
            $facade->doSomething()
        );
    }
}
