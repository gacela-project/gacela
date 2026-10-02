<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\MergedConfigCache;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function count;
use function dirname;
use function file_put_contents;
use function getenv;
use function is_file;
use function mkdir;
use function putenv;
use function rmdir;
use function sprintf;
use function str_replace;
use function sys_get_temp_dir;
use function time;
use function touch;
use function uniqid;
use function unlink;
use function var_export;

final class MergedConfigCacheIntegrationTest extends TestCase
{
    private string $cacheDir;

    private string $fixtureDir;

    private ?string $originalAppEnv = null;

    private string $appDir;

    /** @var list<string> */
    private array $createdFiles = [];

    /** @var list<string> */
    private array $createdDirs = [];

    protected function setUp(): void
    {
        $this->cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '.gacela-cache-' . uniqid('', true);
        $this->fixtureDir = __DIR__ . DIRECTORY_SEPARATOR . 'AutoWarmFixtures';
        $this->appDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-merged-config-app-' . uniqid('', true);
        mkdir($this->appDir . DIRECTORY_SEPARATOR . 'config', 0777, true);

        $env = getenv('APP_ENV');
        $this->originalAppEnv = $env === false ? null : $env;
        putenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        $this->restoreAppEnv();
        $this->removeCacheDir();
        $this->removeAppDir();
        Config::resetInstance();
    }

    public function test_init_loads_from_cache_when_present_and_file_cache_enabled(): void
    {
        $this->writeMergedConfigCacheFile(['from_cache' => 'yes']);

        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(true, $cacheDir);
            $config->resetInMemoryCache();
        });

        self::assertSame('yes', Config::getInstance()->get('from_cache'));
    }

    public function test_init_ignores_cache_when_file_cache_disabled(): void
    {
        $this->writeMergedConfigCacheFile(['from_cache' => 'yes']);

        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(false, $cacheDir);
            $config->resetInMemoryCache();
        });

        self::assertSame('default', Config::getInstance()->get('from_cache', 'default'));
    }

    public function test_auto_warms_merged_config_cache_on_miss_when_file_cache_enabled(): void
    {
        Gacela::bootstrap($this->fixtureDir, $this->autoWarmConfig());

        $filename = Config::getInstance()->mergedConfigCacheFilename();

        self::assertTrue(is_file($filename), 'merged config cache should be auto-warmed on miss');
        self::assertSame('warm_value', Config::getInstance()->get('warm_key'));
        // A multi-key merged config must survive the cache-miss path intact.
        self::assertSame('second_warm_value', Config::getInstance()->get('second_warm_key'));
    }

    public function test_does_not_auto_warm_when_file_cache_disabled(): void
    {
        $cacheDir = $this->cacheDir;
        Gacela::bootstrap($this->fixtureDir, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(false, $cacheDir);
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php');
        });

        $filename = Config::getInstance()->mergedConfigCacheFilename();

        self::assertFalse(is_file($filename));
    }

    public function test_does_not_auto_warm_when_merged_config_is_empty(): void
    {
        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(true, $cacheDir);
            $config->resetInMemoryCache();
        });

        $filename = Config::getInstance()->mergedConfigCacheFilename();

        self::assertFalse(is_file($filename), 'an empty merged config is not worth caching');
    }

    public function test_auto_warmed_cache_is_reused_on_next_bootstrap(): void
    {
        // First bootstrap auto-warms the cache from the fixture config files.
        Gacela::bootstrap($this->fixtureDir, $this->autoWarmConfig());
        $filename = Config::getInstance()->mergedConfigCacheFilename();
        self::assertTrue(is_file($filename));

        // Tamper the warmed values, leaving its source stamps alone, to prove
        // the next bootstrap reads from it instead of re-globbing the files.
        $this->replaceCachedValues($filename, ['warm_key' => 'from_cache']);

        Gacela::bootstrap($this->fixtureDir, $this->autoWarmConfig());

        self::assertSame('from_cache', Config::getInstance()->get('warm_key'));
    }

    public function test_an_edited_config_file_is_read_on_the_next_bootstrap(): void
    {
        $file = $this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        $this->writeAppConfig($file, 'first');
        $this->bootstrapApp();
        self::assertFileExists(Config::getInstance()->mergedConfigCacheFilename());
        self::assertSame('first', Config::getInstance()->get('src'));

        $this->writeAppConfig($file, 'second-value');
        $this->bootstrapApp();

        self::assertSame('second-value', Config::getInstance()->get('src'));
    }

    /**
     * `stat()` gives whole seconds, and a directory on ext4 or NTFS keeps its
     * size when a file is added, so a stamp taken in the second of a change
     * cannot see a second change in that same second.
     */
    public function test_a_source_touched_this_second_is_not_cached_yet(): void
    {
        $file = $this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        file_put_contents($file, "<?php return ['src' => 'now'];");
        $this->createdFiles[] = $file;

        $this->bootstrapApp();

        self::assertSame('now', Config::getInstance()->get('src'));
        self::assertFileDoesNotExist(Config::getInstance()->mergedConfigCacheFilename());
    }

    public function test_a_settled_source_is_cached(): void
    {
        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', 'settled');

        $this->bootstrapApp();

        self::assertFileExists(Config::getInstance()->mergedConfigCacheFilename());
    }

    public function test_a_file_added_to_a_globbed_directory_is_read_on_the_next_bootstrap(): void
    {
        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', 'first');
        $this->bootstrapApp();
        self::assertFileExists(Config::getInstance()->mergedConfigCacheFilename());

        // Sorted after app.php, so it is merged last and wins.
        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'zz.php', 'added');
        $this->bootstrapApp();

        self::assertSame('added', Config::getInstance()->get('src'));
    }

    public function test_a_file_added_under_a_wildcard_directory_is_read_on_the_next_bootstrap(): void
    {
        $config = $this->appDir . DIRECTORY_SEPARATOR . 'config';
        mkdir($config . DIRECTORY_SEPARATOR . 'a');
        mkdir($config . DIRECTORY_SEPARATOR . 'b');
        $this->createdDirs = [$config . DIRECTORY_SEPARATOR . 'a', $config . DIRECTORY_SEPARATOR . 'b'];
        $this->writeAppConfig($config . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'app.php', 'a');
        touch($config . DIRECTORY_SEPARATOR . 'b', time() - 100);
        touch($config, time() - 100);
        $this->bootstrapWith('config/*/app.php');
        self::assertFileExists(Config::getInstance()->mergedConfigCacheFilename());
        self::assertSame('a', Config::getInstance()->get('src'));

        // Inside a directory that already existed: `config` itself is untouched.
        $this->writeAppConfig($config . DIRECTORY_SEPARATOR . 'b' . DIRECTORY_SEPARATOR . 'app.php', 'b');
        $this->bootstrapWith('config/*/app.php');

        self::assertSame('b', Config::getInstance()->get('src'));
    }

    /**
     * `cache:warm` is a deploy step, and its file a deploy artifact: like
     * Laravel's `config:cache`, it is served without a look at its sources
     * until the next warm or `cache:clear`. That is what keeps a hit as cheap
     * as it was before sources were recorded.
     */
    public function test_a_warmed_cache_is_trusted_until_the_next_warm(): void
    {
        $file = $this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        $this->writeAppConfig($file, 'deployed');
        $this->bootstrapApp();
        Config::getInstance()->writeMergedConfigCache();

        $this->writeAppConfig($file, 'edited-after-deploy');
        $this->bootstrapApp();
        self::assertSame('deployed', Config::getInstance()->get('src'));

        Config::getInstance()->writeMergedConfigCache();
        $this->bootstrapApp();
        self::assertSame('edited-after-deploy', Config::getInstance()->get('src'));
    }

    /**
     * The values can depend on code the cache does not read, such as a config
     * class whose output is stored. Watching it rebuilds the cache when it
     * changes, though no config file did.
     */
    public function test_a_watched_file_that_changes_rebuilds_the_cache(): void
    {
        $file = $this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        $this->writeAppConfig($file, 'from-file');
        $watched = $this->appDir . DIRECTORY_SEPARATOR . 'AppConfig.php';
        file_put_contents($watched, '<?php // v1');
        $this->createdFiles[] = $watched;
        touch($watched, time() - 100);
        touch($this->appDir, time() - 100);

        $this->bootstrapWatching(['AppConfig.php']);
        $filename = Config::getInstance()->mergedConfigCacheFilename();
        $this->replaceCachedValues($filename, ['src' => 'stale']);

        $this->bootstrapWatching(['AppConfig.php']);
        self::assertSame('stale', Config::getInstance()->get('src'), 'an untouched watched file keeps the cache');

        file_put_contents($watched, '<?php // version two');
        touch($watched, time() - 50);
        $this->bootstrapWatching(['AppConfig.php']);

        self::assertSame('from-file', Config::getInstance()->get('src'));
    }

    /**
     * A directory's own stamp misses an edit to a file in it, so a glob names
     * the files themselves.
     */
    public function test_a_watched_glob_catches_an_edit_to_a_file_it_matches(): void
    {
        $file = $this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        $this->writeAppConfig($file, 'from-file');
        $src = $this->appDir . DIRECTORY_SEPARATOR . 'src';
        mkdir($src);
        $this->createdDirs[] = $src;
        $watched = $src . DIRECTORY_SEPARATOR . 'AppConfig.php';
        file_put_contents($watched, '<?php // v1');
        $this->createdFiles[] = $watched;
        touch($watched, time() - 100);
        touch($src, time() - 100);

        $this->bootstrapWatching(['src/*.php']);
        $this->replaceCachedValues(Config::getInstance()->mergedConfigCacheFilename(), ['src' => 'stale']);

        file_put_contents($watched, '<?php // version two');
        touch($watched, time() - 50);
        touch($src, time() - 100);
        $this->bootstrapWatching(['src/*.php']);

        self::assertSame('from-file', Config::getInstance()->get('src'));
    }

    /**
     * `__DIR__ . '/AppConfig.php'` in gacela.php is already under the root:
     * prefixing the root again would watch a path that never exists.
     */
    public function test_an_absolute_watch_path_under_the_root_is_taken_as_it_is(): void
    {
        $file = $this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        $this->writeAppConfig($file, 'from-file');
        $watched = $this->appDir . DIRECTORY_SEPARATOR . 'AppConfig.php';
        file_put_contents($watched, '<?php // v1');
        $this->createdFiles[] = $watched;
        touch($watched, time() - 100);
        touch($this->appDir, time() - 100);

        $this->bootstrapWatching([$watched]);
        $this->replaceCachedValues(Config::getInstance()->mergedConfigCacheFilename(), ['src' => 'stale']);

        file_put_contents($watched, '<?php // version two');
        touch($watched, time() - 50);
        $this->bootstrapWatching([$watched]);

        self::assertSame('from-file', Config::getInstance()->get('src'));
    }

    public function test_a_watched_file_created_later_rebuilds_the_cache(): void
    {
        $file = $this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        $this->writeAppConfig($file, 'from-file');
        touch($this->appDir, time() - 100);

        $this->bootstrapWatching(['AppConfig.php']);
        $this->replaceCachedValues(Config::getInstance()->mergedConfigCacheFilename(), ['src' => 'stale']);

        $watched = $this->appDir . DIRECTORY_SEPARATOR . 'AppConfig.php';
        file_put_contents($watched, '<?php // new');
        $this->createdFiles[] = $watched;
        touch($watched, time() - 50);
        touch($this->appDir, time() - 50);
        $this->bootstrapWatching(['AppConfig.php']);

        self::assertSame('from-file', Config::getInstance()->get('src'));
    }

    /**
     * A tool whose users run `cache:warm` while they still edit config asks
     * the warm to be checked like a miss, so an edit is read without a clear.
     */
    public function test_a_verified_warm_reads_a_later_edit(): void
    {
        $file = $this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        $this->writeAppConfig($file, 'warmed');
        $this->bootstrapWatching([], verifiedWarm: true);
        Config::getInstance()->writeMergedConfigCache();
        self::assertFileExists(Config::getInstance()->mergedConfigCacheFilename());

        $this->writeAppConfig($file, 'edited-after-warm');
        $this->bootstrapWatching([], verifiedWarm: true);

        self::assertSame('edited-after-warm', Config::getInstance()->get('src'));
    }

    /**
     * Stamps taken in the second of a change cannot see another change in it,
     * so a verified warm then writes nothing, and removes what an earlier
     * trusted warm left, rather than serve it unchecked.
     */
    public function test_a_verified_warm_of_a_source_touched_this_second_leaves_no_file(): void
    {
        $file = $this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        $this->writeAppConfig($file, 'trusted');
        $this->bootstrapApp();
        Config::getInstance()->writeMergedConfigCache();
        self::assertFileExists(Config::getInstance()->mergedConfigCacheFilename());

        touch($file);
        $this->bootstrapWatching([], verifiedWarm: true);
        $filename = Config::getInstance()->writeMergedConfigCache();

        self::assertFileDoesNotExist($filename);
    }

    /**
     * A worker that bootstraps again without `resetInMemoryCache()` keeps the
     * glob results of the first bootstrap. A rebuild must not read through
     * them, or it stores the old file list under fresh stamps.
     */
    public function test_a_rebuild_in_the_same_process_globs_afresh(): void
    {
        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', 'first');
        $this->bootstrapWith('config/*.php', '', resetInMemoryCache: false);
        self::assertFileExists(Config::getInstance()->mergedConfigCacheFilename());

        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'zz.php', 'added');
        $this->bootstrapWith('config/*.php', '', resetInMemoryCache: false);

        self::assertSame('added', Config::getInstance()->get('src'));
    }

    public function test_a_new_subdirectory_under_a_wildcard_is_read_on_the_next_bootstrap(): void
    {
        $config = $this->appDir . DIRECTORY_SEPARATOR . 'config';
        mkdir($config . DIRECTORY_SEPARATOR . 'a');
        $this->createdDirs = [$config . DIRECTORY_SEPARATOR . 'a'];
        $this->writeAppConfig($config . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'app.php', 'a');
        touch($config, time() - 100);
        $this->bootstrapWith('config/*/app.php');
        self::assertFileExists(Config::getInstance()->mergedConfigCacheFilename());

        mkdir($config . DIRECTORY_SEPARATOR . 'b');
        $this->createdDirs[] = $config . DIRECTORY_SEPARATOR . 'b';
        $this->writeAppConfig($config . DIRECTORY_SEPARATOR . 'b' . DIRECTORY_SEPARATOR . 'app.php', 'b');
        touch($config, time() - 5);
        $this->bootstrapWith('config/*/app.php');

        self::assertSame('b', Config::getInstance()->get('src'));
    }

    public function test_a_local_override_created_later_is_read_on_the_next_bootstrap(): void
    {
        // In a directory of its own, so only watching the override's directory
        // can notice it: the base pattern's directory never changes.
        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', 'first');
        $override = $this->appDir . DIRECTORY_SEPARATOR . 'override';
        mkdir($override);
        $this->createdDirs[] = $override;
        touch($override, time() - 100);
        $this->bootstrapWith('config/*.php', 'override/local.php');
        self::assertFileExists(Config::getInstance()->mergedConfigCacheFilename());

        $this->writeAppConfig($override . DIRECTORY_SEPARATOR . 'local.php', 'local');
        $this->bootstrapWith('config/*.php', 'override/local.php');

        self::assertSame('local', Config::getInstance()->get('src'));
    }

    /**
     * Written by an earlier version, under the name it used: it may hold values
     * from before an edit that version never noticed, so it is not read.
     */
    public function test_a_cache_file_from_an_earlier_version_is_not_read(): void
    {
        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', 'from-source');
        $this->bootstrapApp();
        $current = Config::getInstance()->mergedConfigCacheFilename();
        $earlier = str_replace('gacela-merged-config-v2-', 'gacela-merged-config-', $current);
        unlink($current);
        file_put_contents($earlier, sprintf('<?php return %s;', var_export(['src' => 'from-an-earlier-version'], true)));

        $this->bootstrapApp();

        self::assertSame('from-source', Config::getInstance()->get('src'));
    }

    public function test_a_changed_declaration_is_rebuilt(): void
    {
        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', 'first');
        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'other.php', 'other');
        $this->bootstrapApp();

        $cacheDir = $this->cacheDir;
        Gacela::bootstrap($this->appDir, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(true, $cacheDir);
            $config->resetInMemoryCache();
            $config->addAppConfig('other.php');
        });

        self::assertSame('other', Config::getInstance()->get('src'));
    }

    public function test_setup_config_values_override_cached_values(): void
    {
        $this->writeMergedConfigCacheFile(['shared_key' => 'from_cache']);

        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(true, $cacheDir);
            $config->resetInMemoryCache();
            $config->addAppConfigKeyValue('shared_key', 'from_setup');
        });

        self::assertSame('from_setup', Config::getInstance()->get('shared_key'));
    }

    public function test_write_merged_config_cache_persists_file(): void
    {
        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(true, $cacheDir);
            $config->resetInMemoryCache();
        });

        $filename = Config::getInstance()->writeMergedConfigCache();

        self::assertTrue(is_file($filename));
    }

    public function test_clear_merged_config_cache_removes_file(): void
    {
        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(true, $cacheDir);
            $config->resetInMemoryCache();
        });

        $filename = Config::getInstance()->writeMergedConfigCache();
        self::assertTrue(is_file($filename));

        Config::getInstance()->clearMergedConfigCache();

        self::assertFalse(is_file($filename));
    }

    public function test_env_keys_produce_separate_cache_files(): void
    {
        putenv('APP_ENV=prod');
        $this->writeMergedConfigCacheFile(['env_marker' => 'prod_value'], 'prod');

        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(true, $cacheDir);
            $config->resetInMemoryCache();
        });

        self::assertSame('prod_value', Config::getInstance()->get('env_marker'));
    }

    public function test_cache_file_for_one_env_is_not_used_by_another(): void
    {
        putenv('APP_ENV=prod');
        $this->writeMergedConfigCacheFile(['env_marker' => 'prod_value'], 'prod');
        putenv('APP_ENV=dev');

        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(true, $cacheDir);
            $config->resetInMemoryCache();
        });

        self::assertSame('missing', Config::getInstance()->get('env_marker', 'missing'));
    }

    public function test_apps_sharing_a_cache_dir_do_not_read_each_others_merged_config(): void
    {
        // App A (the AutoWarmFixtures root) warms its merged config into the shared dir.
        Gacela::bootstrap($this->fixtureDir, $this->autoWarmConfig());
        self::assertSame('warm_value', Config::getInstance()->get('warm_key'));

        // App B (this dir, no config files) boots against the same cache dir:
        // it must not be served app A's merged config.
        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(true, $cacheDir);
            $config->resetInMemoryCache();
        });

        self::assertSame('missing', Config::getInstance()->get('warm_key', 'missing'));
    }

    private function autoWarmConfig(): Closure
    {
        $cacheDir = $this->cacheDir;

        return static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(true, $cacheDir);
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php');
        };
    }

    /**
     * @param array<string,mixed> $data
     */
    private function writeMergedConfigCacheFile(array $data, string $env = ''): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        // Filenames are scoped per app root (#465); every test here boots
        // __DIR__, which declares no config path, so there is no source to stamp.
        (new MergedConfigCache($this->cacheDir, $env, __DIR__))->writeTrusted($data);
    }

    /**
     * @param array<string,mixed> $values
     */
    private function replaceCachedValues(string $filename, array $values): void
    {
        /** @var array{values: array<string,mixed>} $data */
        $data = require $filename;
        $data['values'] = $values;

        file_put_contents($filename, sprintf('<?php return %s;', var_export($data, true)));
    }

    /**
     * Backdated, each write later than the one before: a source touched in the
     * current second is not cached yet (see the same-second test), and these
     * tests are about what a cached stamp catches.
     */
    private function writeAppConfig(string $file, string $value): void
    {
        file_put_contents($file, sprintf("<?php return ['src' => %s];", var_export($value, true)));
        $this->createdFiles[] = $file;

        $at = time() - 100 + 10 * count($this->createdFiles);
        touch($file, $at);
        touch(dirname($file), $at);
    }

    private function bootstrapApp(): void
    {
        $this->bootstrapWith('config/*.php', 'config/local.php');
    }

    /**
     * @param list<string> $watched
     */
    private function bootstrapWatching(array $watched, bool $verifiedWarm = false): void
    {
        $cacheDir = $this->cacheDir;
        Gacela::bootstrap($this->appDir, static function (GacelaConfig $config) use ($cacheDir, $watched, $verifiedWarm): void {
            $config->setFileCache(true, $cacheDir);
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php', 'config/local.php');
            $config->addConfigCacheWatch(...$watched);
            if ($verifiedWarm) {
                $config->enableVerifiedConfigCacheWarm();
            }
        });
    }

    private function bootstrapWith(string $path, string $pathLocal = '', bool $resetInMemoryCache = true): void
    {
        $cacheDir = $this->cacheDir;
        Gacela::bootstrap($this->appDir, static function (GacelaConfig $config) use ($cacheDir, $path, $pathLocal, $resetInMemoryCache): void {
            $config->setFileCache(true, $cacheDir);
            if ($resetInMemoryCache) {
                $config->resetInMemoryCache();
            }

            $config->addAppConfig($path, $pathLocal);
        });
    }

    private function removeAppDir(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        foreach ($this->createdDirs as $dir) {
            @rmdir($dir);
        }

        @rmdir($this->appDir . DIRECTORY_SEPARATOR . 'config');
        @rmdir($this->appDir);
    }

    private function removeCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        foreach (glob($this->cacheDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->cacheDir);
    }

    private function restoreAppEnv(): void
    {
        if ($this->originalAppEnv === null) {
            putenv('APP_ENV');
            return;
        }

        putenv('APP_ENV=' . $this->originalAppEnv);
    }
}
