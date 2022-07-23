<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomCacheDirectory;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\ClassNameCache;
use Gacela\Framework\ClassResolver\DocBlockService\CustomServicesCache;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class NoFileCacheFeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfig('config/*.php');
            $config->setCacheDirectory('custom/caching-dir');
            $config->setCacheEnabled(false);
        });
    }

    public function test_custom_caching_dir(): void
    {
        $facade = new Module\Facade();
        self::assertSame('name', $facade->getName());

        self::assertFileDoesNotExist(__DIR__ . '/custom/caching-dir/' . ClassNameCache::CACHE_FILENAME);
        self::assertFileDoesNotExist(__DIR__ . '/custom/caching-dir/' . CustomServicesCache::CACHE_FILENAME);
    }
}
