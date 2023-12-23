<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingConfigFromCustomEnv;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        # Remove the APP_ENV
        putenv('APP_ENV');
    }

    public function test_load_config_from_custom_env_default(): void
    {
        $this->bootstrapGacela();

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'from-default' => 1,
                'from-default-env-override' => 1,
                'from-local-override' => 4,
            ],
            $facade->doSomething(),
        );
    }

    public function test_load_config_from_custom_env_dev(): void
    {
        putenv('APP_ENV=dev');

        $this->bootstrapGacela();

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'from-default' => 1,
                'from-default-env-override' => 2,
                'from-local-override' => 4,
            ],
            $facade->doSomething(),
        );
    }

    public function test_load_config_from_custom_env_prod(): void
    {
        putenv('APP_ENV=prod');

        $this->bootstrapGacela();

        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'from-default' => 1,
                'from-default-env-override' => 3,
                'from-local-override' => 4,
            ],
            $facade->doSomething(),
        );
    }

    private function bootstrapGacela(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php', 'config/local.php');
        });
    }
}
