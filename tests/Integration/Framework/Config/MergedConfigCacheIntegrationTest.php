<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config;

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
use function uniqid;
use function unlink;
use function var_export;

final class MergedConfigCacheIntegrationTest extends TestCase
{
    private string $cacheDir;

    private ?string $originalAppEnv = null;

    protected function setUp(): void
    {
        $this->cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '.gacela-cache-' . uniqid('', true);

        $env = getenv('APP_ENV');
        $this->originalAppEnv = $env === false ? null : $env;
        putenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        $this->restoreAppEnv();
        $this->removeCacheDir();
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

    /**
     * @param array<string,mixed> $data
     */
    private function writeMergedConfigCacheFile(array $data, string $env = ''): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        $suffix = $env !== '' ? '-' . $env : '';
        $filename = $this->cacheDir
            . DIRECTORY_SEPARATOR
            . MergedConfigCache::FILENAME_PREFIX
            . $suffix
            . MergedConfigCache::FILENAME_EXTENSION;

        file_put_contents($filename, sprintf('<?php return %s;', var_export($data, true)));
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
