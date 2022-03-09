<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingConfigWithBootstrapSetup;

use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, [
            'config' => function (ConfigBuilder $configResolver): void {
                $configResolver->add(PhpConfigReader::class, 'custom-config.php', 'custom-config_local.php');
            },
        ]);
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
