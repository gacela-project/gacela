<?php

declare(strict_types=1);

namespace Gacela\Framework\Testing;

use FilesystemIterator;
use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use SplFileInfo;

use function class_exists;
use function is_array;
use function is_dir;
use function is_string;
use function is_subclass_of;
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
     * Resolve another module's Factory to a double, for as long as this test
     * runs.
     *
     * Testing module A in isolation means replacing module B, and until now
     * that was either a container binding -- which only works when B arrives
     * through a Provider -- or a reach into `AnonymousGlobal` with a key format
     * the caller had to know. The Factory is the seam: a consumer constructs
     * B's Facade itself, and every Facade asks the resolver for its Factory.
     *
     * ```php
     * $this->swapModuleFactory(BlogFacade::class, new class() extends BlogFactory {
     *     public function createPostReader(): PostReader { ... }
     * });
     * ```
     *
     * The swap is dropped by {@see resetContainer()}, which `GacelaTestCase`
     * already runs in `tearDown()`.
     *
     * @param class-string<AbstractFacade> $facadeClass the module to replace, named
     *                                                  by the class its consumers use
     */
    protected function swapModuleFactory(string $facadeClass, AbstractFactory $double): void
    {
        $this->swapModulePillar($facadeClass, 'Factory', $double);
    }

    /**
     * @see swapModuleFactory() for how the swap works and when it is dropped
     *
     * @param class-string<AbstractFacade> $facadeClass
     */
    protected function swapModuleConfig(string $facadeClass, AbstractConfig $double): void
    {
        $this->swapModulePillar($facadeClass, 'Config', $double);
    }

    /**
     * @see swapModuleFactory() for how the swap works and when it is dropped
     *
     * @param class-string<AbstractFacade> $facadeClass
     */
    protected function swapModuleProvider(string $facadeClass, AbstractProvider $double): void
    {
        $this->swapModulePillar($facadeClass, 'Provider', $double);
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
        /** @var mixed $config */
        $config = self::readStaticProperty(Config::class, 'instance');

        $configValues = [];
        $appRootDir = null;
        $cacheDir = null;

        if ($config instanceof Config) {
            /** @var mixed $rawConfigValues */
            $rawConfigValues = self::readPrivateProperty($config, 'config');
            /** @var array<string, mixed> $configValues */
            $configValues = is_array($rawConfigValues) ? $rawConfigValues : [];

            /** @var mixed $rawAppRootDir */
            $rawAppRootDir = self::readPrivateProperty($config, 'appRootDir');
            $appRootDir = is_string($rawAppRootDir) ? $rawAppRootDir : null;

            /** @var mixed $rawCacheDir */
            $rawCacheDir = self::readPrivateProperty($config, 'cacheDir');
            $cacheDir = is_string($rawCacheDir) ? $rawCacheDir : null;
        }

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
     * so any derived caches (e.g. ServiceResolverCache) stay empty and
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

        $this->restoreConfigState($snapshot);
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

    /**
     * Puts back the config values, application root and cache directory the
     * snapshot captured. Without this, `restoreContainerState()` left
     * `Config::getInstance()` throwing, and a snapshot advertised as
     * restorable restored a quarter of what it recorded.
     *
     * A fresh `SetupGacela` is used rather than the one that was active:
     * a setup holds closures, and this snapshot is deliberately limited to
     * data that survives serialization. Only the captured values are put back,
     * so no service object is constructed by restoring.
     */
    private function restoreConfigState(ContainerSnapshot $snapshot): void
    {
        $appRootDir = $snapshot->appRootDir();
        $cacheDir = $snapshot->cacheDir();

        if ($appRootDir === null && $cacheDir === null && $snapshot->config() === []) {
            // Captured before Gacela was bootstrapped: there is no config state
            // to restore, and creating one would invent an instance the caller
            // never had.
            return;
        }

        $config = Config::createWithSetup(new SetupGacela());

        self::writePrivateProperty($config, 'config', $snapshot->config());
        // init() re-reads the config files and would overwrite what was just
        // restored, and get() runs it whenever this flag is false.
        self::writePrivateProperty($config, 'initialized', true);

        if ($appRootDir !== null) {
            $config->setAppRootDir($appRootDir);
            self::writeStaticProperty(Gacela::class, 'appRootDir', $appRootDir);
        }

        if ($cacheDir !== null) {
            self::writePrivateProperty($config, 'cacheDir', $cacheDir);
        }
    }

    /**
     * The resolver keys a pillar by its module's namespace and the pillar type,
     * so a double is registered under the same key the module's own class would
     * have taken -- whatever that class is named, and whether or not the module
     * has one at all.
     *
     * @param class-string<AbstractFacade> $facadeClass
     */
    private function swapModulePillar(string $facadeClass, string $resolvableType, object $double): void
    {
        if (!class_exists($facadeClass) || !is_subclass_of($facadeClass, AbstractFacade::class)) {
            throw ModuleDoubleException::notAFacade($facadeClass);
        }

        $namespace = (new ReflectionClass($facadeClass))->getNamespaceName();
        AnonymousGlobal::overrideExistingResolvedClass($namespace . '\\' . $resolvableType, $double);

        // A Facade resolved before the swap holds its Factory in a static, and
        // a Factory holds the container built from its Provider and Config. A
        // swap nothing re-reads is a swap that silently did not happen.
        AbstractFacade::resetCache();
        AbstractFactory::resetCache();
    }

    private function registerContainerTempDirsCleanup(): void
    {
        if ($this->containerTempDirsCleanupRegistered) {
            return;
        }

        $this->containerTempDirsCleanupRegistered = true;

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

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
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
        return self::propertyOf($object, $property)?->getValue($object);
    }

    /**
     * @param  class-string  $className
     */
    private static function readStaticProperty(string $className, string $property): mixed
    {
        return self::propertyOf($className, $property)?->getValue();
    }

    private static function writePrivateProperty(object $object, string $property, mixed $value): void
    {
        self::propertyOf($object, $property)?->setValue($object, $value);
    }

    /**
     * @param  class-string  $className
     */
    private static function writeStaticProperty(string $className, string $property, mixed $value): void
    {
        self::propertyOf($className, $property)?->setValue(null, $value);
    }

    /**
     * Null rather than throwing when the property is absent: these helpers
     * reach into framework internals, and a fixture must not break a test suite
     * because a private field was renamed.
     *
     * @param  class-string|object  $target
     */
    private static function propertyOf(string|object $target, string $property): ?ReflectionProperty
    {
        $reflection = new ReflectionClass($target);

        return $reflection->hasProperty($property)
            ? $reflection->getProperty($property)
            : null;
    }
}
