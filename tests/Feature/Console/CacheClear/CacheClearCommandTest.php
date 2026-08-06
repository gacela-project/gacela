<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CacheClear;

use Gacela\Console\Application\CacheWarm\CacheManager;
use Gacela\Console\Infrastructure\Command\CacheClearCommand;
use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Tester\CommandTester;

use function dirname;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;
use function unlink;

final class CacheClearCommandTest extends TestCase
{
    /**
     * Named as a string rather than `::class`: the class is `@internal`
     * upstream, and reaching into it is the point -- there is no public way to
     * observe a process-global memo being cleared.
     */
    private const string CONTAINER_CACHE_MANAGER = 'Gacela\Container\DependencyCacheManager';

    private string $cacheFile;

    private string $customServicesCacheFile;

    private string $mergedConfigCacheFile;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->enableFileCache(__DIR__ . '/cache');
        });

        $cacheDir = Config::getInstance()->getCacheDir();
        $this->cacheFile = $cacheDir . DIRECTORY_SEPARATOR . ClassNamePhpCache::FILENAME;
        $this->customServicesCacheFile = $cacheDir . DIRECTORY_SEPARATOR . CustomServicesPhpCache::FILENAME;
        $this->mergedConfigCacheFile = Config::getInstance()->mergedConfigCacheFilename();

        $this->removeGeneratedCaches();
    }

    protected function tearDown(): void
    {
        $this->removeGeneratedCaches();
        DirectoryUtil::removeDir(__DIR__ . '/cache');
    }

    public function test_cache_clear_command_is_registered_in_the_console_application(): void
    {
        $command = (new ConsoleBootstrap())->find('cache:clear');

        self::assertSame('cache:clear', $command->getName());
    }

    public function test_cache_clear_removes_an_existing_cache_file(): void
    {
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        file_put_contents($this->cacheFile, '<?php return [];');

        $command = new CommandTester(new CacheClearCommand());
        $exitCode = $command->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Cleared cache file', $command->getDisplay());
        self::assertStringContainsString('Cache cleared successfully!', $command->getDisplay());
        self::assertFileDoesNotExist($this->cacheFile);
    }

    public function test_cache_clear_removes_the_custom_services_cache_file(): void
    {
        $cacheDir = dirname($this->customServicesCacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        file_put_contents($this->customServicesCacheFile, '<?php return [];');

        $command = new CommandTester(new CacheClearCommand());
        $exitCode = $command->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Cleared cache file', $command->getDisplay());
        self::assertStringContainsString('Cache cleared successfully!', $command->getDisplay());
        self::assertFileDoesNotExist($this->customServicesCacheFile);
    }

    public function test_cache_clear_reports_when_no_cache_is_present(): void
    {
        $command = new CommandTester(new CacheClearCommand());
        $exitCode = $command->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No cache files found.', $command->getDisplay());
    }

    /**
     * The container memoizes reflection output -- property plans, `#[Lazy]`,
     * `#[Singleton]`/`#[Factory]`, instantiability -- in statics that outlive
     * every container and that no file on disk holds. A command called
     * `cache:clear` that leaves the largest cache in the process untouched is
     * answering a narrower question than its name.
     *
     * Deliberately not done by `Gacela::resetCache()`: that runs on every
     * `resetInMemoryCache()` bootstrap, and upstream is explicit these memos
     * never go stale, so throwing the reflection away there costs and buys
     * nothing. Here the process ends a moment later.
     */
    public function test_cache_clear_also_clears_the_containers_process_global_memos(): void
    {
        $instantiable = new ReflectionProperty(self::CONTAINER_CACHE_MANAGER, 'instantiable');

        Gacela::container()->get(CacheClearCommand::class);
        self::assertNotSame([], $instantiable->getValue(), sprintf(
            'Precondition: resolving a class must populate %s::$instantiable, or this test proves nothing.',
            self::CONTAINER_CACHE_MANAGER,
        ));

        (new CommandTester(new CacheClearCommand()))->execute([]);

        self::assertSame([], $instantiable->getValue());
    }

    /**
     * Nothing on disk says anything about the in-process memos, so the reset has
     * to happen before the command decides there is nothing to do.
     */
    public function test_the_process_global_memos_are_cleared_even_with_no_cache_files(): void
    {
        $instantiable = new ReflectionProperty(self::CONTAINER_CACHE_MANAGER, 'instantiable');

        Gacela::container()->get(CacheClearCommand::class);
        self::assertNotSame([], $instantiable->getValue());

        $command = new CommandTester(new CacheClearCommand());
        $command->execute([]);

        self::assertStringContainsString('No cache files found.', $command->getDisplay());
        self::assertSame([], $instantiable->getValue());
    }

    public function test_cache_clear_removes_the_merged_config_cache(): void
    {
        Config::getInstance()->writeMergedConfigCache();
        self::assertFileExists($this->mergedConfigCacheFile);

        $command = new CommandTester(new CacheClearCommand());
        $exitCode = $command->execute([]);

        $display = $command->getDisplay();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Cleared merged config cache', $display);
        self::assertStringContainsString($this->mergedConfigCacheFile, $display);
        self::assertFileDoesNotExist($this->mergedConfigCacheFile);
    }

    public function test_cache_clear_lists_every_removed_cache_file_with_its_size(): void
    {
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        file_put_contents($this->cacheFile, '<?php return [];');
        file_put_contents($this->customServicesCacheFile, '<?php return [];');

        $sizes = (new CacheManager())->getExistingCacheFilesWithSize();
        self::assertCount(2, $sizes);

        $command = new CommandTester(new CacheClearCommand());
        $exitCode = $command->execute([]);
        $display = $command->getDisplay();

        self::assertSame(0, $exitCode);

        // Every cache file that existed is reported with the size it had...
        foreach ($sizes as $file => $size) {
            self::assertStringContainsString(sprintf('Cleared cache file: %s (%s)', $file, $size), $display);
        }

        // ...and is actually gone afterwards.
        self::assertFileDoesNotExist($this->cacheFile);
        self::assertFileDoesNotExist($this->customServicesCacheFile);
    }

    private function removeGeneratedCaches(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }

        if (file_exists($this->customServicesCacheFile)) {
            unlink($this->customServicesCacheFile);
        }

        if (file_exists($this->mergedConfigCacheFile)) {
            unlink($this->mergedConfigCacheFile);
        }
    }
}
