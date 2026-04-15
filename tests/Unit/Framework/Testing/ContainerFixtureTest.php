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
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use stdClass;

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
        $prop->setAccessible(true);
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
        $prop->setAccessible(true);

        self::assertNotNull($prop->getValue());

        $this->resetContainer();

        self::assertNull($prop->getValue());
    }

    public function test_reset_container_clears_abstract_facade_factories(): void
    {
        $reflection = new ReflectionClass(AbstractFacade::class);
        $prop = $reflection->getProperty('factories');
        $prop->setAccessible(true);
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

    #[Before]
    protected function setUpContainer(): void
    {
        $this->resetContainer();
    }

    private function readConfigSingleton(): ?Config
    {
        $reflection = new ReflectionClass(Config::class);
        $prop = $reflection->getProperty('instance');
        $prop->setAccessible(true);

        /** @var Config|null $value */
        $value = $prop->getValue();

        return $value;
    }
}
