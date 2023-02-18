<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingMultipleConfig;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\SimpleEnvConfigReader;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config
                ->resetInMemoryCache()
                ->addAppConfig('config/.env*', '', SimpleEnvConfigReader::class)
                ->addAppConfig('config/*.php');
        });
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
            $facade->doSomething(),
        );
    }
}
