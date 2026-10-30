<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CacheWarm;

use Gacela\Console\Infrastructure\Command\CacheWarmCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\ClassResolver\ClassResolverCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\Cache\CacheWarmedEvent;
use Gacela\Framework\Gacela;
use Gacela\Framework\Plugins\Membership\MembershipCache;
use GacelaTest\Feature\Util\DirectoryUtil;
use GacelaTest\Fixtures\ReadOnlyDirTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function count;
use function dirname;
use function file_exists;
use function mkdir;
use function putenv;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

final class CacheWarmCommandTest extends TestCase
{
    use ReadOnlyDirTrait;

    private CommandTester $command;

    private string $cacheFile;

    private string $mergedConfigCacheFile;

    private string $customServicesCacheFile;

    private string $membershipCacheFile;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->enableFileCache(__DIR__ . '/cache');
        });

        $this->cacheFile = AbstractPhpFileCache::absoluteFilename(
            Config::getInstance()->getCacheDir(),
            ClassNamePhpCache::FILENAME,
            Config::getInstance()->getAppRootDir(),
            ClassResolverCache::bootstrapFingerprint(),
        );
        $this->mergedConfigCacheFile = Config::getInstance()->mergedConfigCacheFilename();
        $this->customServicesCacheFile = AbstractPhpFileCache::absoluteFilename(
            Config::getInstance()->getCacheDir(),
            CustomServicesPhpCache::FILENAME,
            Config::getInstance()->getAppRootDir(),
        );

        $this->membershipCacheFile = MembershipCache::forScan(Config::getInstance()->getCacheDir(), Config::getInstance()->getAppRootDir(), Config::getInstance()->getSetupGacela()->getAppModulePaths(), Config::getInstance()->getSetupGacela()->getProjectNamespaces())->path();

        $this->removeGeneratedCaches();

        $this->command = new CommandTester(new CacheWarmCommand());
    }

    protected function tearDown(): void
    {
        $this->restoreReadOnlyDirs();
        $this->removeGeneratedCaches();
    }

    public function test_cache_warm_fails_when_enabled_caches_cannot_be_written(): void
    {
        $dir = $this->createReadOnlyDirOrSkip('cache-warm');
        $previous = getenv('GACELA_CACHE_DIR');
        putenv('GACELA_CACHE_DIR=' . $dir);
        try {
            Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
                $config->resetInMemoryCache()->enableFileCache();
            });
            $command = new CommandTester(new CacheWarmCommand());
            $exit = $command->execute(['--attributes' => true]);
        } finally {
            putenv($previous === false ? 'GACELA_CACHE_DIR' : 'GACELA_CACHE_DIR=' . $previous);
        }

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Cache file was not created', $command->getDisplay());
        self::assertStringContainsString('could not write the membership cache', $command->getDisplay());
    }

    public function test_cache_warm_does_not_report_a_stale_merged_file_as_written(): void
    {
        $previous = getenv('GACELA_CACHE_DIR');
        try {
            $this->createReadOnlyDirOrSkip('cache-warm-stale', static function (string $dir): void {
                putenv('GACELA_CACHE_DIR=' . $dir);
                Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
                    $config->resetInMemoryCache()->enableFileCache();
                });
                file_put_contents(Config::getInstance()->mergedConfigCacheFilename(), '<?php return ["stale" => true];');
            });
            $filename = Config::getInstance()->mergedConfigCacheFilename();
            $command = new CommandTester(new CacheWarmCommand());
            $exit = $command->execute([]);
        } finally {
            putenv($previous === false ? 'GACELA_CACHE_DIR' : 'GACELA_CACHE_DIR=' . $previous);
        }

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Could not write: ' . $filename, $command->getDisplay());
        self::assertStringNotContainsString('Merged config size:', $command->getDisplay());
        self::assertStringEqualsFile($filename, '<?php return ["stale" => true];');
    }

    #[DataProvider('persistenceInvalidations')]
    public function test_a_retained_stale_file_makes_persistence_invalidation_fail(bool $verified): void
    {
        $source = __DIR__ . DIRECTORY_SEPARATOR . 'gacela-invalidate-source-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($source, $verified ? '<?php return ["current" => true];' : '<?php return ["handler" => static fn (): string => "current"];');
        touch($source, time() + 60);
        $previous = getenv('GACELA_CACHE_DIR');
        try {
            $dir = $this->createReadOnlyDirOrSkip('cache-invalidation', static function (string $dir) use ($source, $verified): void {
                putenv('GACELA_CACHE_DIR=' . $dir);
                Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($source, $verified): void {
                    $config->resetInMemoryCache()->enableFileCache();
                    $config->addAppConfig(basename($source));
                    if ($verified) {
                        $config->enableVerifiedConfigCacheWarm();
                    }
                });
                (new CommandTester(new CacheWarmCommand()))->execute([]);
                file_put_contents(Config::getInstance()->mergedConfigCacheFilename(), '<?php return ["stale" => true];');
            });
            $filename = Config::getInstance()->mergedConfigCacheFilename();
            $command = new CommandTester(new CacheWarmCommand());
            self::assertSame(Command::FAILURE, $command->execute([]));
            self::assertStringContainsString('Could not write: ' . $filename, $command->getDisplay());
            self::assertStringNotContainsString('Merged config size:', $command->getDisplay());
            self::assertContains($filename, FileCache::failedWrites());
            self::assertStringEqualsFile($filename, '<?php return ["stale" => true];');
            chmod($dir, 0755);

            self::assertSame(Command::SUCCESS, (new CommandTester(new CacheWarmCommand()))->execute([]));
            self::assertFileDoesNotExist($filename);
            self::assertNotContains($filename, FileCache::failedWrites());
        } finally {
            putenv($previous === false ? 'GACELA_CACHE_DIR' : 'GACELA_CACHE_DIR=' . $previous);
            unlink($source);
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function persistenceInvalidations(): iterable
    {
        yield 'non-exportable value' => [false];
        yield 'source-stamp race' => [true];
    }

    public function test_membership_write_failure_is_fatal_and_a_successful_retry_clears_it(): void
    {
        if (!is_dir(dirname($this->membershipCacheFile))) {
            mkdir(dirname($this->membershipCacheFile), 0777, true);
        }

        mkdir($this->membershipCacheFile);
        try {
            $command = new CommandTester(new CacheWarmCommand());
            self::assertSame(Command::FAILURE, $command->execute(['--attributes' => true]));
            self::assertStringContainsString('could not write the membership cache to ' . $this->membershipCacheFile, $command->getDisplay());
            self::assertFileExists($this->cacheFile);
            self::assertFileExists($this->mergedConfigCacheFile);
            rmdir($this->membershipCacheFile);

            self::assertSame(Command::SUCCESS, (new CommandTester(new CacheWarmCommand()))->execute(['--attributes' => true]));
            self::assertNotContains($this->membershipCacheFile, FileCache::failedWrites());
        } finally {
            if (is_dir($this->membershipCacheFile)) {
                rmdir($this->membershipCacheFile);
            }
        }
    }

    public function test_an_unrelated_process_write_failure_does_not_fail_a_healthy_warm(): void
    {
        self::assertSame(Command::SUCCESS, $this->command->execute([]));
        $unrelated = $this->uncreatableDir('unrelated-cache') . DIRECTORY_SEPARATOR . 'cache.php';
        self::assertFalse(FileCache::writeAtomically($unrelated, []));
        $command = new CommandTester(new CacheWarmCommand());

        self::assertSame(Command::SUCCESS, $command->execute([]));
        self::assertStringNotContainsString($unrelated, $command->getDisplay());
        self::assertContains($unrelated, FileCache::failedWrites());
    }

    public function test_cache_warm_dispatches_cache_warmed_event(): void
    {
        $warmedEvents = [];
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$warmedEvents): void {
            $config->resetInMemoryCache();
            $config->enableFileCache(__DIR__ . '/cache');
            $config->registerSpecificListener(
                CacheWarmedEvent::class,
                static function (object $event) use (&$warmedEvents): void {
                    $warmedEvents[] = $event;
                },
            );
        });

        $this->command->execute([]);

        self::assertCount(1, $warmedEvents);
        self::assertInstanceOf(CacheWarmedEvent::class, $warmedEvents[0]);
        self::assertGreaterThanOrEqual(0, $warmedEvents[0]->moduleCount());
        self::assertGreaterThanOrEqual(0, $warmedEvents[0]->failedCount());
        self::assertGreaterThanOrEqual(0, $warmedEvents[0]->skippedCount());
    }

    /**
     * The event is the only place a listener can see the outcome, and a listener
     * alerting on `failedCount() > 0` has to stay quiet on a healthy warm. This
     * application warms without a single failure, so anything above zero here is
     * the skipped count wearing the wrong name.
     */
    public function test_a_healthy_warm_reports_no_failures(): void
    {
        $warmedEvents = [];
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$warmedEvents): void {
            $config->resetInMemoryCache();
            $config->enableFileCache(__DIR__ . '/cache');
            $config->registerSpecificListener(
                CacheWarmedEvent::class,
                static function (CacheWarmedEvent $event) use (&$warmedEvents): void {
                    $warmedEvents[] = $event;
                },
            );
        });

        $this->command->execute([]);

        self::assertSame(0, $warmedEvents[0]->failedCount());
    }

    public function test_cache_warm_creates_cache_file(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Warming Gacela cache', $output);
        self::assertStringContainsString('Cache warming complete!', $output);
        self::assertStringContainsString('Modules processed:', $output);
        self::assertStringContainsString('Classes resolved:', $output);
        self::assertStringContainsString('Classes skipped:', $output);
        self::assertStringContainsString('Classes failed:', $output);
        self::assertStringContainsString('Time taken:', $output);
        self::assertStringContainsString('Memory used:', $output);
    }

    public function test_cache_warm_with_clear_option(): void
    {
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        file_put_contents($this->cacheFile, '<?php return [];');
        self::assertFileExists($this->cacheFile);

        $this->command->execute(['--clear' => true]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Cleared existing cache', $output);
        self::assertStringContainsString('Cache warming complete!', $output);
    }

    public function test_cache_warm_finds_test_modules(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Found', $output);
        self::assertStringContainsString('modules', $output);
    }

    public function test_cache_warm_displays_statistics(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();

        self::assertMatchesRegularExpression('/Modules processed:\s+\d+/', $output);
        self::assertMatchesRegularExpression('/Classes resolved:\s+\d+/', $output);
        self::assertMatchesRegularExpression('/Classes skipped:\s+\d+/', $output);
        self::assertMatchesRegularExpression('/Classes failed:\s+\d+/', $output);
        self::assertMatchesRegularExpression('/Time taken:\s+[\d.]+\s+seconds/', $output);
        self::assertMatchesRegularExpression('/Memory used:\s+[\d.]+\s+(B|KB|MB)/', $output);
    }

    public function test_cache_warm_success_exit_code(): void
    {
        $exitCode = $this->command->execute([]);

        self::assertSame(0, $exitCode);
    }

    public function test_cache_warm_with_attributes_option(): void
    {
        $this->command->execute(['--attributes' => true]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Warming Gacela cache', $output);
        self::assertStringContainsString('Cache warming complete!', $output);
        self::assertSame(0, $this->command->getStatusCode());
    }

    /**
     * The --attributes flag is only observable through its side effect: warming the
     * doc-block/attribute cache writes the custom-services cache file. Asserting on
     * the command output alone cannot tell the two modes apart, because the flag
     * adds no output of its own.
     */
    public function test_cache_warm_with_attributes_caches_more_docblock_services(): void
    {
        $this->command->execute([]);
        $withoutAttributes = $this->customServicesCacheEntryCount();

        $this->freshBootstrap();
        (new CommandTester(new CacheWarmCommand()))->execute(['--attributes' => true]);
        $withAttributes = $this->customServicesCacheEntryCount();

        self::assertGreaterThan(
            $withoutAttributes,
            $withAttributes,
            '--attributes must pre-resolve additional doc-block services',
        );
    }

    public function test_cache_warm_with_attributes_stores_the_plugin_membership(): void
    {
        $this->command->execute(['--attributes' => true]);

        self::assertFileExists($this->membershipCacheFile);
        self::assertStringContainsString('Membership cache:', $this->command->getDisplay());
    }

    public function test_cache_warm_without_attributes_leaves_the_plugin_membership_alone(): void
    {
        $this->command->execute([]);

        self::assertFileDoesNotExist($this->membershipCacheFile);
    }

    public function test_cache_warm_with_all_options(): void
    {
        $this->command->execute([
            '--clear' => true,
            '--attributes' => true,
        ]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Cleared existing cache', $output);
        self::assertStringContainsString('Cache warming complete!', $output);
        self::assertSame(0, $this->command->getStatusCode());
    }

    public function test_cache_warm_reports_the_written_cache_files(): void
    {
        $this->command->execute([]);

        $display = $this->command->getDisplay();

        self::assertFileExists($this->cacheFile);
        self::assertStringContainsString('Cache file: ' . $this->cacheFile, $display);
        self::assertMatchesRegularExpression('/Cache size: [\d.]+ (B|KB|MB)/', $display);
        self::assertStringContainsString('Merged config cache: ' . $this->mergedConfigCacheFile, $display);
        self::assertMatchesRegularExpression('/Merged config size: [\d.]+ (B|KB|MB)/', $display);
    }

    public function test_cache_warm_warns_when_no_cache_file_is_written(): void
    {
        $this->withEmptyCacheDir(static function (): void {
            $command = new CommandTester(new CacheWarmCommand());
            $command->execute([]);

            $display = $command->getDisplay();

            self::assertStringContainsString(
                'Warning: Cache file was not created. File caching might be disabled.',
                $display,
            );
            self::assertSame(Command::SUCCESS, $command->getStatusCode());
            self::assertStringContainsString('Enable file caching in your gacela.php configuration:', $display);
            self::assertStringNotContainsString('Cache size:', $display);
            self::assertFileDoesNotExist(Config::getInstance()->mergedConfigCacheFilename());
        });
    }

    public function test_cache_warm_fails_when_a_discovered_module_cannot_be_warmed(): void
    {
        $cacheDir = sys_get_temp_dir() . '/gacela-cache-warm-' . bin2hex(random_bytes(4));
        $brokenModules = dirname(__DIR__, 3) . '/Integration/Console/CacheWarm/FacadeWarm/Domain';
        putenv('GACELA_CACHE_DIR=' . $cacheDir);

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($brokenModules): void {
            $config->resetInMemoryCache();
            $config->enableFileCache();
            $config->setAppModulePaths([$brokenModules]);
            $config->setProjectNamespaces(['GacelaTest\\Integration\\Console\\CacheWarm\\FacadeWarm\\Domain']);
        });

        try {
            $command = new CommandTester(new CacheWarmCommand());
            $command->execute([]);

            self::assertSame(Command::FAILURE, $command->getStatusCode());
            self::assertMatchesRegularExpression('/Classes failed:\s+[1-9]\d*/', $command->getDisplay());
        } finally {
            putenv('GACELA_CACHE_DIR');
            DirectoryUtil::removeDir($cacheDir);
        }
    }

    public function test_cache_warm_with_clear_option_removes_the_previous_cache_file(): void
    {
        $this->withEmptyCacheDir(static function (string $cacheDir): void {
            $staleCacheFile = $cacheDir . DIRECTORY_SEPARATOR . ClassNamePhpCache::FILENAME;
            file_put_contents($staleCacheFile, '<?php return [];');

            $command = new CommandTester(new CacheWarmCommand());
            $command->execute(['--clear' => true]);

            // File caching is off, so nothing writes the file back: whatever is
            // left on disk is what --clear did.
            self::assertFileDoesNotExist($staleCacheFile);
            self::assertStringContainsString('Cleared existing cache', $command->getDisplay());
        });
    }

    public function test_cache_warm_warns_when_modules_cannot_be_discovered(): void
    {
        $missingRoot = sys_get_temp_dir() . '/gacela-cache-warm-missing-' . bin2hex(random_bytes(4));

        Gacela::bootstrap($missingRoot, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });

        $command = new CommandTester(new CacheWarmCommand());
        $command->execute([]);

        $display = $command->getDisplay();

        self::assertStringContainsString(
            'Warning: Some modules could not be discovered due to errors',
            $display,
        );
        self::assertStringContainsString('  Error: ', $display);
        self::assertStringContainsString('Found 0 modules', $display);
        self::assertSame(Command::FAILURE, $command->getStatusCode());
    }

    /**
     * Runs the given code against a cache directory this test owns and that
     * nothing writes into, so the files found there are the ones it put there.
     *
     * @param callable(string):void $call
     */
    private function withEmptyCacheDir(callable $call): void
    {
        $cacheDir = sys_get_temp_dir() . '/gacela-cache-warm-' . bin2hex(random_bytes(4));
        mkdir($cacheDir, 0777, true);
        putenv('GACELA_CACHE_DIR=' . $cacheDir);

        try {
            Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
                $config->resetInMemoryCache();
                $config->setFileCache(false);
            });

            $call($cacheDir);
        } finally {
            putenv('GACELA_CACHE_DIR');
            DirectoryUtil::removeDir($cacheDir);
        }
    }

    private function freshBootstrap(): void
    {
        $this->removeGeneratedCaches();

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->enableFileCache(__DIR__ . '/cache');
        });
    }

    private function customServicesCacheEntryCount(): int
    {
        if (!file_exists($this->customServicesCacheFile)) {
            return 0;
        }

        /** @var array<string,mixed> $entries */
        $entries = require $this->customServicesCacheFile;

        return count($entries);
    }

    private function removeGeneratedCaches(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }

        if (file_exists($this->mergedConfigCacheFile)) {
            unlink($this->mergedConfigCacheFile);
        }

        if (file_exists($this->customServicesCacheFile)) {
            unlink($this->customServicesCacheFile);
        }

        if (file_exists($this->membershipCacheFile)) {
            unlink($this->membershipCacheFile);
        }
    }
}
