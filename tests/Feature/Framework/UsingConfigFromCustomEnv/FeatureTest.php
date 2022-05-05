<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingConfigFromCustomEnv;

use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    private SetupGacela $setup;

    protected function setUp(): void
    {
        $this->setup = (new SetupGacela())
            ->setConfigFn(static function (ConfigBuilder $configBuilder): void {
                $configBuilder->add('config/*.php', 'config/local.php');
            });
    }

    public function tearDown(): void
    {
        # Remove the APP_ENV
        putenv('APP_ENV');
    }

    public function test_load_config_from_custom_env_default(): void
    {
        Gacela::bootstrap(__DIR__, $this->setup);
        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'from-default' => 1,
                'from-default-env-override' => 1,
                'from-local-override' => 4,
            ],
            $facade->doSomething()
        );
    }

    public function test_load_config_from_custom_env_dev(): void
    {
        putenv('APP_ENV=dev');
        Gacela::bootstrap(__DIR__, $this->setup);
        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'from-default' => 1,
                'from-default-env-override' => 2,
                'from-local-override' => 4,
            ],
            $facade->doSomething()
        );
    }

    public function test_load_config_from_custom_env_prod(): void
    {
        putenv('APP_ENV=prod');
        Gacela::bootstrap(__DIR__, $this->setup);
        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'from-default' => 1,
                'from-default-env-override' => 3,
                'from-local-override' => 4,
            ],
            $facade->doSomething()
        );
    }
}
