<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Testing;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Locator;
use Gacela\Framework\Gacela;
use Gacela\Framework\Testing\ContainerFixture;
use Gacela\Framework\Testing\ContainerSnapshot;
use GacelaTest\Unit\Framework\Testing\Fixture\ChildOfContainerFixtureUser;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use stdClass;

use function dirname;
use function sprintf;

final class ContainerFixtureTest extends TestCase
{
    use ContainerFixture;

    protected function tearDown(): void
    {
        $this->cleanupContainerTempDirs();
        $this->resetContainer();
    }

    public function test_reset_container_clears_in_memory_cache(): void
    {
        $cache = new InMemoryCache('some-key');
        $cache->put('SomeClass', 'ResolvedClass');

        self::assertNotSame([], InMemoryCache::all(), 'cache should be populated before reset');

        $this->resetContainer();

        self::assertSame([], InMemoryCache::all());
    }

    public function test_reset_container_clears_anonymous_globals(): void
    {
        $anonFactory = new class() extends \Gacela\Framework\AbstractFactory {
        };

        Gacela::addGlobal($anonFactory, 'FixtureContext');

        self::assertNotNull(
            AnonymousGlobal::getByKey(AnonymousGlobal::createCacheKey('FixtureContext', 'Factory')),
        );

        $this->resetContainer();

        self::assertNull(
            AnonymousGlobal::getByKey(AnonymousGlobal::createCacheKey('FixtureContext', 'Factory')),
        );
    }

    public function test_reset_container_clears_config_singleton(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });

        self::assertInstanceOf(Config::class, $this->readConfigSingleton());

        $this->resetContainer();

        self::assertNull($this->readConfigSingleton());
    }

    public function test_reset_container_clears_class_resolver_cache(): void
    {
        $reflection = new ReflectionClass(AbstractClassResolver::class);
        $prop = $reflection->getProperty('cachedInstances');
        $prop->setValue(null, ['some-key' => new stdClass()]);

        /** @var array<string, mixed> $before */
        $before = $prop->getValue();
        self::assertNotSame([], $before);

        $this->resetContainer();

        /** @var array<string, mixed> $after */
        $after = $prop->getValue();
        self::assertSame([], $after);
    }

    public function test_reset_container_clears_locator_instance(): void
    {
        Locator::getInstance();
        $reflection = new ReflectionClass(Locator::class);
        $prop = $reflection->getProperty('instance');

        self::assertNotNull($prop->getValue());

        $this->resetContainer();

        self::assertNull($prop->getValue());
    }

    public function test_reset_container_clears_abstract_factory_containers(): void
    {
        $reflection = new ReflectionClass(\Gacela\Framework\AbstractFactory::class);
        $prop = $reflection->getProperty('containers');
        $prop->setValue(null, ['some-key' => new stdClass()]);

        $this->resetContainer();

        /** @var array<string, mixed> $after */
        $after = $prop->getValue();
        self::assertSame([], $after);
    }

    public function test_reset_container_clears_abstract_facade_factories(): void
    {
        $reflection = new ReflectionClass(AbstractFacade::class);
        $prop = $reflection->getProperty('factories');
        $prop->setValue(null, [AbstractFacade::class => new class() extends \Gacela\Framework\AbstractFactory {
        }]);

        /** @var array<string, mixed> $before */
        $before = $prop->getValue();
        self::assertNotSame([], $before);

        $this->resetContainer();

        /** @var array<string, mixed> $after */
        $after = $prop->getValue();
        self::assertSame([], $after);
    }

    public function test_reset_gacela_singletons_is_an_alias_for_reset_container(): void
    {
        (new InMemoryCache('alias-test'))->put('K', 'V');

        $this->resetGacelaSingletons();

        self::assertSame([], InMemoryCache::all());
    }

    public function test_reset_container_runs_in_under_ten_milliseconds(): void
    {
        // Populate some state so reset has real work to do.
        (new InMemoryCache('perf'))->put('A', 'B');
        Gacela::addGlobal(new class() extends \Gacela\Framework\AbstractFactory {
        }, 'PerfContext');

        $start = hrtime(true);
        $this->resetContainer();
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        self::assertLessThan(10.0, $elapsedMs, sprintf('resetContainer took %.2fms', $elapsedMs));
    }

    public function test_capture_container_state_returns_snapshot_with_current_caches(): void
    {
        (new InMemoryCache('captured'))->put('Class', 'Resolved');

        $snapshot = $this->captureContainerState();

        self::assertInstanceOf(ContainerSnapshot::class, $snapshot);
        self::assertSame(['captured' => ['Class' => 'Resolved']], $snapshot->inMemoryCache());
    }

    public function test_capture_container_state_without_bootstrap_yields_empty_config(): void
    {
        $snapshot = $this->captureContainerState();

        self::assertSame([], $snapshot->config());
        self::assertNull($snapshot->appRootDir());
        self::assertNull($snapshot->cacheDir());
    }

    public function test_capture_container_state_includes_app_root_and_cache_dir_once_known(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
        $cacheDir = Config::getInstance()->getCacheDir();

        $snapshot = $this->captureContainerState();

        self::assertSame(__DIR__, $snapshot->appRootDir());
        self::assertSame($cacheDir, $snapshot->cacheDir());
    }

    /**
     * The snapshot records config values, app root and cache dir, and restore
     * used to drop all three -- leaving Config::getInstance() throwing on a
     * state the API says it just restored.
     */
    public function test_restore_container_state_reinstates_config_values(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfigKeyValue('restored-key', 'restored-value');
        });
        $snapshot = $this->captureContainerState();

        $this->resetContainer();

        $this->restoreContainerState($snapshot);

        self::assertSame('restored-value', Config::getInstance()->get('restored-key'));
    }

    public function test_restore_container_state_reinstates_the_app_root_and_cache_dir(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
        $cacheDir = Config::getInstance()->getCacheDir();
        $snapshot = $this->captureContainerState();

        $this->resetContainer();

        $this->restoreContainerState($snapshot);

        self::assertSame(__DIR__, Gacela::rootDir());
        self::assertSame(__DIR__, Config::getInstance()->getAppRootDir());
        self::assertSame($cacheDir, Config::getInstance()->getCacheDir());
    }

    /**
     * The case the fixture exists for: swap state out, bootstrap something
     * else, then put the original back. Restoring has to win over whatever the
     * second bootstrap left behind.
     */
    public function test_restore_container_state_wins_over_a_different_bootstrap(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfigKeyValue('which', 'first');
        });
        $snapshot = $this->captureContainerState();

        Gacela::bootstrap(dirname(__DIR__), static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfigKeyValue('which', 'second');
        });
        self::assertSame('second', Config::getInstance()->get('which'));

        $this->restoreContainerState($snapshot);

        self::assertSame('first', Config::getInstance()->get('which'));
        self::assertSame(__DIR__, Gacela::rootDir());
    }

    /**
     * The cache dir has to be restored explicitly, not merely recomputed.
     * `getCacheDir()` derives a default from the *setup*, and restore builds a
     * fresh `SetupGacela` -- so a non-default directory is only preserved if
     * the captured value is actually written back.
     */
    public function test_restore_container_state_reinstates_a_non_default_cache_dir(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(true, 'custom-cache-dir');
        });

        // Materialise it, so the snapshot captures a value rather than null.
        $cacheDir = Config::getInstance()->getCacheDir();
        // A fresh SetupGacela, which is what restore builds, would compute this
        // instead -- so the assertion below can only pass if the captured value
        // was written back rather than recomputed.
        self::assertNotSame(sys_get_temp_dir(), $cacheDir);

        $snapshot = $this->captureContainerState();
        $this->resetContainer();

        $this->restoreContainerState($snapshot);

        self::assertSame($cacheDir, Config::getInstance()->getCacheDir());
    }

    /**
     * Only the app root was ever established: no config values were read and
     * the cache dir was never materialised. There is still state worth
     * restoring, so the "nothing captured" shortcut must not trigger.
     */
    public function test_restore_container_state_reinstates_an_app_root_captured_on_its_own(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $snapshot = $this->captureContainerState();
        self::assertSame(__DIR__, $snapshot->appRootDir());
        self::assertNull($snapshot->cacheDir());
        self::assertSame([], $snapshot->config());

        $this->resetContainer();
        $this->restoreContainerState($snapshot);

        // Asserted through Config rather than Gacela::rootDir(): resetCache()
        // drops the Config instance but leaves Gacela's own static app root
        // standing, so rootDir() answers the same with or without a restore.
        self::assertSame(__DIR__, Config::getInstance()->getAppRootDir());
    }

    /**
     * A snapshot taken before bootstrap has nothing to restore, and inventing a
     * Config instance would hand the caller state they never had.
     */
    public function test_restore_container_state_without_captured_config_creates_no_instance(): void
    {
        $snapshot = $this->captureContainerState();

        $this->restoreContainerState($snapshot);

        self::assertNull(self::readStaticProperty(Config::class, 'instance'));
    }

    /**
     * The reflection helpers reach into framework internals, so a private field
     * that gets renamed has to degrade to a no-op rather than take a whole test
     * suite down with an Error.
     */
    public function test_the_reflection_helpers_tolerate_a_property_that_does_not_exist(): void
    {
        $object = new stdClass();

        self::assertNull(self::readPrivateProperty($object, 'nothingHere'));
        self::assertNull(self::readStaticProperty(Config::class, 'nothingHere'));

        self::writePrivateProperty($object, 'nothingHere', 'value');
        self::writeStaticProperty(Config::class, 'nothingHere', 'value');

        self::assertFalse(property_exists($object, 'nothingHere'));
    }

    public function test_restore_container_state_reinstates_in_memory_cache(): void
    {
        (new InMemoryCache('to-restore'))->put('Foo', 'Bar');
        $snapshot = $this->captureContainerState();

        $this->resetContainer();
        self::assertSame([], InMemoryCache::all());

        $this->restoreContainerState($snapshot);

        self::assertSame(['to-restore' => ['Foo' => 'Bar']], InMemoryCache::all());
    }

    public function test_container_temp_dir_returns_unique_existing_directory(): void
    {
        $dir1 = $this->containerTempDir();
        $dir2 = $this->containerTempDir();

        self::assertDirectoryExists($dir1);
        self::assertDirectoryExists($dir2);
        self::assertNotSame($dir1, $dir2);
    }

    public function test_cleanup_container_temp_dirs_removes_created_directories(): void
    {
        $dir = $this->containerTempDir();
        self::assertDirectoryExists($dir);

        $this->cleanupContainerTempDirs();

        self::assertDirectoryDoesNotExist($dir);
    }

    public function test_cleanup_removes_nested_files_and_subdirectories(): void
    {
        $dir = $this->containerTempDir();
        $nested = $dir . DIRECTORY_SEPARATOR . 'nested';
        mkdir($nested, 0777, true);
        file_put_contents($nested . DIRECTORY_SEPARATOR . 'file.txt', 'payload');

        $this->cleanupContainerTempDirs();

        self::assertDirectoryDoesNotExist($dir);
    }

    public function test_protected_fixture_methods_are_callable_from_a_subclass(): void
    {
        $child = new ChildOfContainerFixtureUser();

        $child->doResetContainer();
        $child->doResetGacelaSingletons();

        $snapshot = $child->doCaptureContainerState();
        $child->doRestoreContainerState($snapshot);

        $dir = $child->doContainerTempDir();
        self::assertDirectoryExists($dir);

        $child->doCleanupContainerTempDirs();
        self::assertDirectoryDoesNotExist($dir);
    }

    public function test_capture_container_state_reads_config_internals_when_bootstrapped(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
            $config->addAppConfigKeyValue('fixture-key', 'fixture-value');
        });
        Config::getInstance()->get('fixture-key');

        $snapshot = $this->captureContainerState();

        self::assertSame('fixture-value', $snapshot->config()['fixture-key'] ?? null);
        self::assertSame(__DIR__, $snapshot->appRootDir());
    }

    public function test_restore_container_state_drops_state_added_after_capture(): void
    {
        (new InMemoryCache('kept'))->put('A', '1');
        $snapshot = $this->captureContainerState();

        (new InMemoryCache('stale'))->put('B', '2');

        $this->restoreContainerState($snapshot);

        self::assertSame(['kept' => ['A' => '1']], InMemoryCache::all());
    }

    public function test_container_temp_dir_lives_in_system_temp_with_random_suffix(): void
    {
        $dir = $this->containerTempDir();

        $prefix = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-container-fixture-';
        self::assertStringStartsWith($prefix, $dir);
        self::assertMatchesRegularExpression(
            '/^gacela-container-fixture-[0-9a-f]{16}$/',
            basename($dir),
        );
    }

    public function test_temp_dir_cleanup_registration_is_recorded(): void
    {
        $this->containerTempDir();

        $prop = (new ReflectionClass($this))->getProperty('containerTempDirsCleanupRegistered');

        self::assertTrue($prop->getValue($this));
    }

    #[Before]
    protected function setUpContainer(): void
    {
        $this->resetContainer();
    }

    private function readConfigSingleton(): ?Config
    {
        $reflection = new ReflectionClass(Config::class);
        $prop = $reflection->getProperty('instance');

        /** @var Config|null $value */
        $value = $prop->getValue();

        return $value;
    }
}
