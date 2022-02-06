<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingMultipleConfig;

use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\UsingMultipleConfig\LocalConfig\Domain\SimpleEnvConfigReader;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        $globalServices = [
            'config' => [
                [
                    'type' => 'env',
                    'path' => 'config/.env*',
                ],
                [
                    'type' => 'php',
                    'path' => 'config/*.php',
                ],
            ],
            'config-readers' =>  [
                'php' => new PhpConfigReader(),
                'env' => new SimpleEnvConfigReader(),
            ],
        ];

        Gacela::bootstrap(__DIR__, $globalServices);
    }

    public function test_load_multiple_config_files(): void
    {
        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'config-env' => 1,
                'config-php' => 3,
                'override' => 4,
            ],
            $facade->doSomething()
        );
    }
}
