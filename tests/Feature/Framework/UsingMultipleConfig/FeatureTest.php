<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingMultipleConfig;

use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\UsingMultipleConfig\LocalConfig\Domain\SimpleEnvConfigReader;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        $globalServices = [
            'config' => [
                ['path' => 'config/.env*', 'reader' => new SimpleEnvConfigReader()],
                ['path' => 'config/*.php', 'reader' => new PhpConfigReader()],
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
                'local' => 5,
            ],
            $facade->doSomething()
        );
    }
}
