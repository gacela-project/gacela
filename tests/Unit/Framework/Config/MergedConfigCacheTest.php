<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Cache\WritableDirectory;
use Gacela\Framework\Config\MergedConfigCache;
use GacelaTest\Fixtures\ReadOnlyDirTrait;
use PHPUnit\Framework\TestCase;

use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class MergedConfigCacheTest extends TestCase
{
    use ReadOnlyDirTrait;

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-merged-config-test-' . uniqid('', true);
        WritableDirectory::resetCache();
    }

    protected function tearDown(): void
    {
        WritableDirectory::resetCache();
        $this->restoreReadOnlyDirs();
        $this->removeCacheDirIfExists();
    }

    public function test_write_is_best_effort_when_the_cache_directory_cannot_be_created(): void
    {
        $cache = new MergedConfigCache($this->uncreatableDir());

        $cache->write(['key' => 'value']);

        self::assertFalse($cache->exists());
    }

    public function test_write_is_best_effort_when_the_cache_directory_is_read_only(): void
    {
        $cache = new MergedConfigCache($this->createReadOnlyDirOrSkip('merged-config-readonly'));

        $cache->write(['key' => 'value']);

        self::assertFalse($cache->exists());
    }

    public function test_exists_is_false_when_file_not_written(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);

        self::assertFalse($cache->exists());
    }

    public function test_write_creates_the_cache_file(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);

        $cache->write(['key' => 'value']);

        self::assertTrue($cache->exists());
    }

    public function test_load_returns_written_data(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->write(['key' => 'value', 'nested' => ['a' => 1]]);

        self::assertSame(['key' => 'value', 'nested' => ['a' => 1]], $cache->load());
    }

    public function test_write_overwrites_previous_content(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->write(['old' => 'data']);

        $cache->write(['new' => 'data']);

        self::assertSame(['new' => 'data'], $cache->load());
    }

    public function test_clear_removes_the_cache_file(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->write(['key' => 'value']);

        $cache->clear();

        self::assertFalse($cache->exists());
    }

    public function test_clear_is_noop_when_file_does_not_exist(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);

        $cache->clear();

        self::assertFalse($cache->exists());
    }

    public function test_filename_has_no_env_suffix_when_env_empty(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);

        self::assertStringEndsWith(
            MergedConfigCache::FILENAME_PREFIX . MergedConfigCache::FILENAME_EXTENSION,
            $cache->filename(),
        );
    }

    public function test_filename_includes_env_suffix_when_env_set(): void
    {
        $cache = new MergedConfigCache($this->cacheDir, 'prod');

        self::assertStringEndsWith(
            MergedConfigCache::FILENAME_PREFIX . '-prod' . MergedConfigCache::FILENAME_EXTENSION,
            $cache->filename(),
        );
    }

    public function test_different_envs_produce_isolated_cache_files(): void
    {
        $prod = new MergedConfigCache($this->cacheDir, 'prod');
        $dev = new MergedConfigCache($this->cacheDir, 'dev');

        $prod->write(['app' => 'prod']);
        $dev->write(['app' => 'dev']);

        self::assertSame(['app' => 'prod'], $prod->load());
        self::assertSame(['app' => 'dev'], $dev->load());
    }

    public function test_different_app_roots_produce_isolated_cache_files_in_a_shared_dir(): void
    {
        $appA = new MergedConfigCache($this->cacheDir, '', '/srv/app-a');
        $appB = new MergedConfigCache($this->cacheDir, '', '/srv/app-b');

        $appA->write(['app' => 'a']);
        $appB->write(['app' => 'b']);

        self::assertNotSame($appA->filename(), $appB->filename());
        self::assertSame(['app' => 'a'], $appA->load());
        self::assertSame(['app' => 'b'], $appB->load());
    }

    public function test_same_app_root_produces_a_stable_filename(): void
    {
        $first = new MergedConfigCache($this->cacheDir, '', '/srv/app-a');
        $second = new MergedConfigCache($this->cacheDir, '', '/srv/app-a');

        self::assertSame($first->filename(), $second->filename());
    }

    public function test_filename_keeps_legacy_name_without_app_root(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);

        self::assertStringEndsWith(
            MergedConfigCache::FILENAME_PREFIX . MergedConfigCache::FILENAME_EXTENSION,
            $cache->filename(),
        );
    }

    public function test_app_scoped_filename_keeps_env_suffix(): void
    {
        $cache = new MergedConfigCache($this->cacheDir, 'prod', '/srv/app-a');

        self::assertStringEndsWith('-prod' . MergedConfigCache::FILENAME_EXTENSION, $cache->filename());
        self::assertStringContainsString(MergedConfigCache::FILENAME_PREFIX . '-', $cache->filename());
    }

    public function test_clear_also_removes_a_legacy_unscoped_cache_file(): void
    {
        $legacy = new MergedConfigCache($this->cacheDir);
        $legacy->write(['stale' => 'legacy']);

        $scoped = new MergedConfigCache($this->cacheDir, '', '/srv/app-a');
        $scoped->write(['fresh' => 'scoped']);

        $scoped->clear();

        self::assertFalse($scoped->exists());
        self::assertFalse($legacy->exists());
    }

    public function test_write_creates_cache_directory_when_missing(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);

        $cache->write(['key' => 'value']);

        self::assertDirectoryExists($this->cacheDir);
    }

    private function removeCacheDirIfExists(): void
    {
        foreach (glob($this->cacheDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->cacheDir);
    }
}
