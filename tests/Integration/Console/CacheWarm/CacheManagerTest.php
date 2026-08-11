<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\CacheWarm;

use Gacela\Console\Application\CacheWarm\CacheManager;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\ClassResolver\ClassResolverCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function str_repeat;
use function uniqid;

use function unlink;

use const DIRECTORY_SEPARATOR;

final class CacheManagerTest extends TestCase
{
    private string $cacheDir;

    private CacheManager $cacheManager;

    protected function setUp(): void
    {
        $this->cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '.gacela-cache-' . uniqid('', true);
        mkdir($this->cacheDir, 0o777, true);

        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->resetInMemoryCache();
            $config->setFileCache(true, $cacheDir);
        });

        $this->cacheManager = new CacheManager();
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->cacheDir . DIRECTORY_SEPARATOR . '*') as $file) {
            @unlink((string) $file);
        }

        @rmdir($this->cacheDir);
    }

    /**
     * The path carries a hash of the app root. The cache dir can be shared --
     * it defaults to the system temp dir -- and without the hash two
     * applications on one machine read and write the same file.
     */
    public function test_cache_file_path_points_at_the_current_bootstraps_class_name_cache(): void
    {
        self::assertSame(
            AbstractPhpFileCache::absoluteFilename(
                Config::getInstance()->getCacheDir(),
                ClassNamePhpCache::FILENAME,
                Config::getInstance()->getAppRootDir(),
                ClassResolverCache::bootstrapFingerprint(),
            ),
            $this->cacheManager->getCacheFilePath(),
        );
    }

    public function test_two_app_roots_do_not_share_a_cache_file(): void
    {
        $dir = Config::getInstance()->getCacheDir();

        self::assertNotSame(
            AbstractPhpFileCache::absoluteFilename($dir, ClassNamePhpCache::FILENAME, '/srv/app-one'),
            AbstractPhpFileCache::absoluteFilename($dir, ClassNamePhpCache::FILENAME, '/srv/app-two'),
        );
    }

    public function test_missing_cache_file_has_no_size(): void
    {
        self::assertFalse($this->cacheManager->cacheFileExists());
        self::assertSame(0, $this->cacheManager->getCacheFileSize());
        self::assertSame('0 B', $this->cacheManager->getFormattedCacheFileSize());
    }

    public function test_existing_cache_file_reports_the_bytes_on_disk(): void
    {
        $this->writeCacheFile(ClassNamePhpCache::FILENAME, 2048);

        self::assertTrue($this->cacheManager->cacheFileExists());
        self::assertSame(2048, $this->cacheManager->getCacheFileSize());
        self::assertSame('2.00 KB', $this->cacheManager->getFormattedCacheFileSize());
    }

    public function test_every_managed_cache_file_is_listed_with_its_size(): void
    {
        $classNameCache = $this->writeCacheFile(ClassNamePhpCache::FILENAME, 1024);
        $customServicesCache = $this->writeCacheFile(CustomServicesPhpCache::FILENAME, 512);

        // The fingerprinted class-name file is discovered by the glob after
        // the fixed spellings, so it lists last (#681).
        self::assertSame([
            $customServicesCache => '512 B',
            $classNameCache => '1.00 KB',
        ], $this->cacheManager->getExistingCacheFilesWithSize());
    }

    public function test_only_the_cache_files_that_exist_are_listed(): void
    {
        $customServicesCache = $this->writeCacheFile(CustomServicesPhpCache::FILENAME, 256);

        self::assertSame(
            [$customServicesCache => '256 B'],
            $this->cacheManager->getExistingCacheFilesWithSize(),
        );
    }

    public function test_no_cache_file_at_all_lists_nothing(): void
    {
        self::assertSame([], $this->cacheManager->getExistingCacheFilesWithSize());
    }

    public function test_clear_cache_removes_every_managed_cache_file(): void
    {
        $classNameCache = $this->writeCacheFile(ClassNamePhpCache::FILENAME, 16);
        $customServicesCache = $this->writeCacheFile(CustomServicesPhpCache::FILENAME, 16);

        $this->cacheManager->clearCache();

        self::assertFileDoesNotExist($classNameCache);
        self::assertFileDoesNotExist($customServicesCache);
        self::assertSame([], $this->cacheManager->getExistingCacheFilesWithSize());
    }

    private function writeCacheFile(string $filename, int $bytes): string
    {
        $path = AbstractPhpFileCache::absoluteFilename(
            Config::getInstance()->getCacheDir(),
            $filename,
            Config::getInstance()->getAppRootDir(),
            // Only the class-name cache carries the bootstrap fingerprint (#681).
            $filename === ClassNamePhpCache::FILENAME ? ClassResolverCache::bootstrapFingerprint() : '',
        );

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o777, true);
        }

        file_put_contents($path, str_repeat('x', $bytes));

        return $path;
    }
}
