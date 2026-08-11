<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FileCache;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\ClassResolver\ClassResolverCache;
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

        DirectoryUtil::removeDir(__DIR__ . '/custom');
    }

    protected function setUp(): void
    {
        DirectoryUtil::removeDir(__DIR__ . '/custom');
    }

    protected function tearDown(): void
    {
        putenv('GACELA_CACHE_DIR');
    }

    public function test_custom_cache_dir(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->enableFileCache('/custom/cache-dir');
        });

        $facade = new Module\Facade();
        self::assertSame('name', $facade->getName());

        self::assertFileExists(AbstractPhpFileCache::absoluteFilename(rtrim(__DIR__ . '/custom/cache-dir/', '/'), ClassNamePhpCache::FILENAME, __DIR__, ClassResolverCache::bootstrapFingerprint()));
        self::assertFileExists(AbstractPhpFileCache::absoluteFilename(rtrim(__DIR__ . '/custom/cache-dir/', '/'), CustomServicesPhpCache::FILENAME, __DIR__));
    }

    public function test_custom_env_gacela_cache_dir(): void
    {
        putenv('GACELA_CACHE_DIR=' . __DIR__ . '/custom/cache-dir');

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->enableFileCache('/custom/this-will-be-overwritten');
        });

        $facade = new Module\Facade();
        self::assertSame('name', $facade->getName());

        self::assertFileExists(AbstractPhpFileCache::absoluteFilename(rtrim(__DIR__ . '/custom/cache-dir/', '/'), ClassNamePhpCache::FILENAME, __DIR__, ClassResolverCache::bootstrapFingerprint()));
        self::assertFileExists(AbstractPhpFileCache::absoluteFilename(rtrim(__DIR__ . '/custom/cache-dir/', '/'), CustomServicesPhpCache::FILENAME, __DIR__));

        self::assertFileDoesNotExist(AbstractPhpFileCache::absoluteFilename(rtrim(__DIR__ . '/custom/this-will-be-overwritten/', '/'), ClassNamePhpCache::FILENAME, __DIR__, ClassResolverCache::bootstrapFingerprint()));
        self::assertFileDoesNotExist(AbstractPhpFileCache::absoluteFilename(rtrim(__DIR__ . '/custom/this-will-be-overwritten/', '/'), CustomServicesPhpCache::FILENAME, __DIR__));
    }

    public function test_custom_cache_dir_but_cache_disable(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false, '/custom/cache-dir');
        });

        $facade = new Module\Facade();
        self::assertSame('name', $facade->getName());

        self::assertFileDoesNotExist(AbstractPhpFileCache::absoluteFilename(rtrim(__DIR__ . '/custom/cache-dir/', '/'), ClassNamePhpCache::FILENAME, __DIR__, ClassResolverCache::bootstrapFingerprint()));
        self::assertFileDoesNotExist(AbstractPhpFileCache::absoluteFilename(rtrim(__DIR__ . '/custom/cache-dir/', '/'), CustomServicesPhpCache::FILENAME, __DIR__));
    }
}
