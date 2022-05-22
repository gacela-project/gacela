<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingConfigWithBootstrapSetup;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        $configFn = static function (GacelaConfig $config): void {
            $config->addAppConfig('custom-config.php', 'custom-config_local.php');
        };

        Gacela::bootstrap(__DIR__, $configFn);
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
