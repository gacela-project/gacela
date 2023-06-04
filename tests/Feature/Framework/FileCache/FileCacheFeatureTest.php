<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FileCache;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class FileCacheFeatureTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });

        DirectoryUtil::removeDir(__DIR__ . '/custom/cache-dir');
    }

    protected function setUp(): void
    {
        DirectoryUtil::removeDir(__DIR__ . '/custom/cache-dir');
    }

    public function test_custom_cache_dir(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->enableFileCache('/custom/cache-dir');
        });

        $facade = new Module\Facade();
        self::assertSame('name', $facade->getName());

        self::assertFileExists(__DIR__ . '/custom/cache-dir/' . ClassNamePhpCache::FILENAME);
        self::assertFileExists(__DIR__ . '/custom/cache-dir/' . CustomServicesPhpCache::FILENAME);
    }

    public function test_custom_cache_dir_but_cache_disable(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false, '/custom/cache-dir');
        });

        $facade = new Module\Facade();
        self::assertSame('name', $facade->getName());

        self::assertFileDoesNotExist(__DIR__ . '/custom/cache-dir/' . ClassNamePhpCache::FILENAME);
        self::assertFileDoesNotExist(__DIR__ . '/custom/cache-dir/' . CustomServicesPhpCache::FILENAME);
    }
}
