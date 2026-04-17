<?php

declare(strict_types=1);

namespace Gacela\Framework\Testing;

use FilesystemIterator;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

use function is_dir;
use function register_shutdown_function;
use function sprintf;

/**
 * PHPUnit trait that provides a one-liner helper to reset Gacela's
 * container, config, and in-memory caches between test methods.
 *
 * The typical usage pattern combines this trait with PHPUnit's
 * `#[Before]` attribute so the reset runs before every test method:
 *
 * ```php
 * use Gacela\Framework\Testing\ContainerFixture;
 * use PHPUnit\Framework\Attributes\Before;
 * use PHPUnit\Framework\TestCase;
 *
 * final class MyTest extends TestCase
 * {
 *     use ContainerFixture;
 *
 *     #[Before]
 *     protected function setUpContainer(): void
 *     {
 *         $this->resetContainer();
 *     }
 *
 *     public function test_it_works(): void
 *     {
 *         // Gacela state is guaranteed fresh here.
 *     }
 * }
 * ```
 *
 * The trait also offers {@see captureContainerState()} and
 * {@see restoreContainerState()} for tests that need to swap state in
 * and out explicitly, plus {@see containerTempDir()} for a unique
 * auto-cleaned scratch directory.
 */
trait ContainerFixture
{
    /** @var list<string> */
    private array $containerTempDirs = [];

    private bool $containerTempDirsCleanupRegistered = false;

    /**
     * Reset every Gacela singleton + in-memory cache. Drop-in replacement
     * for the ad-hoc `::resetCache()` / `::resetInstance()` sequence in
     * `setUp()`; runs in well under 10ms on a medium app.
     */
    protected function resetContainer(): void
    {
        Gacela::resetCache();
    }

    /**
     * Alias for {@see resetContainer()}. Useful when the test wants to
     * emphasise that the reset is about Gacela's singletons specifically
     * rather than a user-owned container.
     */
    protected function resetGacelaSingletons(): void
    {
        $this->resetContainer();
    }

    /**
     * Capture a snapshot of the current Gacela state.
     *
     * The snapshot covers the in-memory class-name cache, the active
     * config values, the configured application root directory and the
     * cache directory. It does not capture resolved service instances
     * because those may hold non-serializable resources.
     */
    protected function captureContainerState(): ContainerSnapshot
    {
        $config = self::readStaticProperty(Config::class, 'instance');

        $configValues = [];
        $appRootDir = null;
        $cacheDir = null;

        if ($config instanceof Config) {
            $configValues = self::readPrivateProperty($config, 'config') ?? [];
            $appRootDir = self::readPrivateProperty($config, 'appRootDir');
            $cacheDir = self::readPrivateProperty($config, 'cacheDir');
        }

        /** @var array<string, mixed> $configValues */
        /** @var ?string $appRootDir */
        /** @var ?string $cacheDir */
        return new ContainerSnapshot(
            inMemoryCache: InMemoryCache::all(),
            config: $configValues,
            appRootDir: $appRootDir,
            cacheDir: $cacheDir,
        );
    }

    /**
     * Restore a previously captured snapshot of the container state.
     *
     * This resets the current singletons first and then re-applies the
     * captured in-memory cache. It does not rerun the bootstrap cycle,
     * so any derived caches (e.g. DocBlockResolverCache) stay empty and
     * will be lazily rebuilt.
     */
    protected function restoreContainerState(ContainerSnapshot $snapshot): void
    {
        $this->resetContainer();

        foreach ($snapshot->inMemoryCache() as $key => $entries) {
            $cache = new InMemoryCache($key);
            foreach ($entries as $cacheKey => $className) {
                $cache->put($cacheKey, $className);
            }
        }
    }

    /**
     * Create (on first access) and return a unique temporary directory
     * for the current test method. The directory is automatically
     * removed at the end of the PHP process via a shutdown function,
     * so tests do not need to clean up manually.
     */
    protected function containerTempDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'gacela-container-fixture-' . bin2hex(random_bytes(8));

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Failed to create temp dir: %s', $dir));
        }

        $this->containerTempDirs[] = $dir;
        $this->registerContainerTempDirsCleanup();

        return $dir;
    }

    /**
     * Remove all temporary directories created via {@see containerTempDir()}
     * during the current test method. Called automatically from a shutdown
     * function and callable as a PHPUnit `#[After]` hook when the user
     * wants synchronous cleanup between methods.
     */
    protected function cleanupContainerTempDirs(): void
    {
        foreach ($this->containerTempDirs as $dir) {
            self::removeDirectoryRecursively($dir);
        }

        $this->containerTempDirs = [];
    }

    private function registerContainerTempDirsCleanup(): void
    {
        if ($this->containerTempDirsCleanupRegistered) {
            return;
        }

        $this->containerTempDirsCleanupRegistered = true;

        /** @psalm-suppress UnusedFunctionCall */
        register_shutdown_function(function (): void {
            $this->cleanupContainerTempDirs();
        });
    }

    private static function removeDirectoryRecursively(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $entry) {
            /** @var SplFileInfo $entry */
            $path = $entry->getPathname();
            if ($entry->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private static function readPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionClass($object);
        if (!$reflection->hasProperty($property)) {
            return null;
        }

        $prop = $reflection->getProperty($property);

        return $prop->getValue($object);
    }

    /**
     * @param  class-string  $className
     */
    private static function readStaticProperty(string $className, string $property): mixed
    {
        $reflection = new ReflectionClass($className);
        if (!$reflection->hasProperty($property)) {
            return null;
        }

        $prop = $reflection->getProperty($property);

        return $prop->getValue();
    }
}
