<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingGacelaFileFromCustomEnv;

use Gacela\Framework\Bootstrap\GacelaConfig;
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
        $this->bootstrapGacela();

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'default_key' => 'from:default',
                'key' => 'from:default',
            ],
            $facade->doSomething(),
        );
    }

    public function test_load_gacela_dev_file(): void
    {
        putenv('APP_ENV=dev');

        $this->bootstrapGacela();

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'default_key' => 'from:default',
                'key' => 'from:dev',
            ],
            $facade->doSomething(),
        );
    }

    public function test_load_gacela_prod_file(): void
    {
        putenv('APP_ENV=prod');

        $this->bootstrapGacela();

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'default_key' => 'from:default',
                'key' => 'from:prod',
            ],
            $facade->doSomething(),
        );
    }

    public function test_load_gacela_default_file_if_custom_does_not_exists(): void
    {
        putenv('APP_ENV=custom');

        $this->bootstrapGacela();

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'default_key' => 'from:default',
                'key' => 'from:default',
            ],
            $facade->doSomething(),
        );
    }

    private function bootstrapGacela(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }
}
