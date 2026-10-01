<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\MergedConfigCache;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function getenv;
use function is_file;
use function mkdir;
use function putenv;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

final class MergedConfigCacheIntegrationTest extends TestCase
{
    /** The signature of an application that declares no config path. */
    private const NOTHING_DECLARED = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

    private string $cacheDir;

    private string $fixtureDir;

    private ?string $originalAppEnv = null;

    private string $appDir;

    /** @var list<string> */
    private array $createdFiles = [];

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
        self::assertSame('first', Config::getInstance()->get('src'));

        $this->writeAppConfig($file, 'second-value');
        $this->bootstrapApp();

        self::assertSame('second-value', Config::getInstance()->get('src'));
    }

    public function test_a_file_added_to_a_globbed_directory_is_read_on_the_next_bootstrap(): void
    {
        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', 'first');
        $this->bootstrapApp();

        // Sorted after app.php, so it is merged last and wins.
        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'zz.php', 'added');
        $this->bootstrapApp();

        self::assertSame('added', Config::getInstance()->get('src'));
    }

    public function test_a_local_override_created_later_is_read_on_the_next_bootstrap(): void
    {
        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', 'first');
        $this->bootstrapApp();

        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'local.php', 'local');
        $this->bootstrapApp();

        self::assertSame('local', Config::getInstance()->get('src'));
    }

    /**
     * Written before the cache carried its sources, so nothing can say it is
     * still right. Serving it is what kept an upgraded application on its
     * stale config.
     */
    public function test_a_cache_file_without_sources_is_rebuilt(): void
    {
        $this->writeAppConfig($this->appDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php', 'from-source');
        $this->bootstrapApp();
        $filename = Config::getInstance()->mergedConfigCacheFilename();
        file_put_contents($filename, sprintf('<?php return %s;', var_export(['src' => 'legacy'], true)));

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
        (new MergedConfigCache($this->cacheDir, $env, __DIR__))->write($data, self::NOTHING_DECLARED);
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

    private function writeAppConfig(string $file, string $value): void
    {
        file_put_contents($file, sprintf("<?php return ['src' => %s];", var_export($value, true)));
        $this->createdFiles[] = $file;
    }

    private function bootstrapApp(): void
    {
        $cacheDir = $this->cacheDir;
        Gacela::bootstrap($this->appDir, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->setFileCache(true, $cacheDir);
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php', 'config/local.php');
        });
    }

    private function removeAppDir(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
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
