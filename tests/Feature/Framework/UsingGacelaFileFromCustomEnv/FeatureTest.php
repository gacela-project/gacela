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

    public function test_load_gacela_default_file(): void
    {
        Gacela::bootstrap(__DIR__);

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'default_key' => 'from:default',
                'key' => 'from:default',
            ],
            $facade->doSomething()
        );
    }

    public function test_load_gacela_dev_file(): void
    {
        putenv('APP_ENV=dev');

        Gacela::bootstrap(__DIR__);

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'default_key' => 'from:default',
                'key' => 'from:dev',
            ],
            $facade->doSomething()
        );
    }

    public function test_load_gacela_prod_file(): void
    {
        putenv('APP_ENV=prod');

        Gacela::bootstrap(__DIR__);

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'default_key' => 'from:default',
                'key' => 'from:prod',
            ],
            $facade->doSomething()
        );
    }

    public function test_load_gacela_default_file_if_custom_does_not_exists(): void
    {
        putenv('APP_ENV=custom');

        Gacela::bootstrap(__DIR__);

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'default_key' => 'from:default',
                'key' => 'from:default',
            ],
            $facade->doSomething()
        );
    }
}
