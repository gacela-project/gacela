<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use Gacela\SymfonyBridge\GacelaCacheWarmer;
use RuntimeException;

use function glob;
use function unlink;

final class GacelaCacheWarmerTest extends SymfonyBridgeTestCase
{
    private const APP_ROOT = __DIR__ . '/Fixtures';

    private const CACHE_DIR = self::APP_ROOT . '/warmer-cache';

    /**
     * Only ever this fixture's own directory.
     *
     * Deriving the directory to empty from `Config::getCacheDir()` looked
     * tidier and was not: with the file cache off that answers the *system*
     * temp directory, and this loop then walked it deleting other processes'
     * files. A cleanup must name what it created.
     */
    protected function tearDown(): void
    {
        // Warming writes more than the file asserted on -- the merged-config
        // cache too -- and a directory left behind is one the repo's own
        // tooling then tries to format.
        foreach (glob(self::CACHE_DIR . '/*') ?: [] as $leftover) {
            unlink($leftover);
        }

        @rmdir(self::CACHE_DIR);

        parent::tearDown();
    }

    /**
     * A project that warms nothing still boots, just colder.
     */
    public function test_it_is_optional(): void
    {
        self::assertTrue((new GacelaCacheWarmer())->isOptional());
    }

    public function test_it_writes_the_cache_symfony_asked_it_to_warm(): void
    {
        $this->bootstrapWithFileCache(true);

        $preloaded = (new GacelaCacheWarmer())->warmUp(self::CACHE_DIR);

        self::assertSame([], $preloaded, 'gacela caches are read at runtime, not opcache-preloaded');
        self::assertFileExists($this->classNameCacheFile());
    }

    /**
     * The same rule `vendor/bin/gacela cache:warm` follows: with the file cache
     * off there is nothing to write, and writing anyway would leave a cache the
     * application never reads.
     */
    public function test_it_writes_nothing_when_the_file_cache_is_off(): void
    {
        $this->bootstrapWithFileCache(false);

        (new GacelaCacheWarmer())->warmUp(self::CACHE_DIR);

        self::assertFileDoesNotExist($this->classNameCacheFile());
    }

    /**
     * Asked of the framework rather than rebuilt here: a cache directory is
     * resolved against the application root, so a path guessed by a test can
     * point at somewhere nothing was ever written.
     */
    private function classNameCacheFile(): string
    {
        try {
            $config = Config::getInstance();
        } catch (RuntimeException) {
            // Nothing bootstrapped: there is no cache directory to speak of,
            // which is not the same as one that happens to be empty.
            return '';
        }

        return AbstractPhpFileCache::absoluteFilename(
            $config->getCacheDir(),
            ClassNamePhpCache::FILENAME,
            $config->getAppRootDir(),
        );
    }

    private function bootstrapWithFileCache(bool $enabled): void
    {
        Gacela::bootstrap(self::APP_ROOT, static function (GacelaConfig $config) use ($enabled): void {
            $config->resetInMemoryCache();
            $config->setFileCache($enabled, self::CACHE_DIR);
        });
    }
}
