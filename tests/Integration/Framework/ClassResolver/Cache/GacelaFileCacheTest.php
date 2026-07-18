<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\Cache;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class GacelaFileCacheTest extends TestCase
{
    protected function setUp(): void
    {
        GacelaFileCache::resetCache();
    }

    protected function tearDown(): void
    {
        GacelaFileCache::resetCache();
        Gacela::resetCache();
    }

    public function test_truthy_non_bool_config_value_enables_the_cache(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
            $config->addAppConfigKeyValue(GacelaFileCache::KEY_ENABLED, 1);
        });

        $cache = new GacelaFileCache(Config::getInstance());

        self::assertTrue($cache->isEnabled());
    }

    public function test_falsy_non_bool_config_value_disables_the_cache(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(true);
            $config->addAppConfigKeyValue(GacelaFileCache::KEY_ENABLED, 0);
        });

        $cache = new GacelaFileCache(Config::getInstance());

        self::assertFalse($cache->isEnabled());
    }
}
