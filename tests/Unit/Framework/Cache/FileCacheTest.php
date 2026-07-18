<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Cache;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\FileCacheStats;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use ReflectionMethod;
use RuntimeException;

use function count;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function function_exists;
use function glob;
use function is_dir;
use function mkdir;
use function restore_error_handler;
use function set_error_handler;
use function sys_get_temp_dir;
use function uniqid;

final class FileCacheTest extends TestCase
{
    private string $cacheDir;

    /** @var FileCache<mixed> */
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/gacela-filecache-test-' . uniqid('', true);
        $this->cache = new FileCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        DirectoryUtil::removeDir($this->cacheDir);
    }

    public function test_has_returns_false_for_missing_key(): void
    {
        self::assertFalse($this->cache->has('missing'));
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        self::assertNull($this->cache->get('missing'));
    }

    public function test_put_and_get_round_trip(): void
    {
        $this->cache->put('key', 'value');

        self::assertTrue($this->cache->has('key'));
        self::assertSame('value', $this->cache->get('key'));
    }

    public function test_delete_removes_an_existing_cache_file(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
        $file = $this->cacheDir . '/some-cache.php';
        file_put_contents($file, '<?php return [];');

        FileCache::delete($file);

        self::assertFileDoesNotExist($file);
    }

    public function test_delete_is_silent_when_the_file_is_absent(): void
    {
        $missing = $this->cacheDir . '/never-created.php';

        // Clearing an already-gone cache file must not emit a PHP warning.
        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new RuntimeException($errstr);
        });

        try {
            FileCache::delete($missing);
            $this->addToAssertionCount(1);
        } finally {
            restore_error_handler();
        }

        self::assertFileDoesNotExist($missing);
    }

    public function test_put_stores_array_value(): void
    {
        $data = ['foo' => 'bar', 'baz' => 42];
        $this->cache->put('arr', $data);

        self::assertSame($data, $this->cache->get('arr'));
    }

    public function test_put_stores_integer_value(): void
    {
        $this->cache->put('int', 99);

        self::assertSame(99, $this->cache->get('int'));
    }

    public function test_put_overwrites_existing_key(): void
    {
        $this->cache->put('key', 'first');
        $this->cache->put('key', 'second');

        self::assertSame('second', $this->cache->get('key'));
    }

    public function test_forget_removes_a_key(): void
    {
        $this->cache->put('key', 'value');
        $this->cache->forget('key');

        self::assertFalse($this->cache->has('key'));
        self::assertNull($this->cache->get('key'));
    }

    public function test_forget_purges_file_on_disk(): void
    {
        $this->cache->put('key', 'value');

        $filesBefore = glob($this->cacheDir . '/*.php') ?: [];
        self::assertCount(1, $filesBefore);

        $this->cache->forget('key');

        $filesAfter = glob($this->cacheDir . '/*.php') ?: [];
        self::assertCount(0, $filesAfter);
    }

    public function test_forget_drops_pending_batch_entry(): void
    {
        $this->cache->beginBatch();
        $this->cache->put('key', 'value');
        $this->cache->forget('key');
        $this->cache->commitBatch();

        self::assertFalse($this->cache->has('key'));

        $fresh = new FileCache($this->cacheDir);
        self::assertFalse($fresh->has('key'));
    }

    public function test_forget_missing_key_is_noop(): void
    {
        $this->cache->forget('nonexistent');

        self::assertFalse($this->cache->has('nonexistent'));
    }

    public function test_clear_removes_all_entries(): void
    {
        $this->cache->put('a', 1);
        $this->cache->put('b', 2);
        $this->cache->clear();

        self::assertFalse($this->cache->has('a'));
        self::assertFalse($this->cache->has('b'));
    }

    public function test_clear_removes_disk_files(): void
    {
        $this->cache->put('a', 1);
        $this->cache->put('b', 2);

        self::assertGreaterThan(0, count(glob($this->cacheDir . '/*.php') ?: []));

        $this->cache->clear();

        self::assertCount(0, glob($this->cacheDir . '/*.php') ?: []);
    }

    public function test_data_persists_across_instances(): void
    {
        $this->cache->put('persisted', 'hello');

        $fresh = new FileCache($this->cacheDir);

        self::assertSame('hello', $fresh->get('persisted'));
    }

    public function test_constructor_creates_directory_when_missing(): void
    {
        $dir = sys_get_temp_dir() . '/gacela-filecache-fresh-' . uniqid('', true);
        self::assertDirectoryDoesNotExist($dir);

        new FileCache($dir);

        self::assertDirectoryExists($dir);
        DirectoryUtil::removeDir($dir);
    }

    public function test_constructor_trims_whitespace_from_directory(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-filecache-trim-' . uniqid('', true);
        $cache = new FileCache('  ' . $dir . '  ');

        self::assertSame($dir, $cache->directory);
        self::assertDirectoryExists($dir);
        DirectoryUtil::removeDir($dir);
    }

    public function test_constructor_collapses_duplicate_separators(): void
    {
        $dir = sys_get_temp_dir() . '/gacela-filecache-dup-' . uniqid('', true);
        $cache = new FileCache($dir . '//nested///deep/');

        self::assertStringNotContainsString('//', $cache->directory);
        self::assertDirectoryExists($cache->directory);
        DirectoryUtil::removeDir($cache->directory);
    }

    public function test_normalize_strips_prefix_when_windows_absolute_path_is_embedded(): void
    {
        // Regression: caller concats getcwd() with an already-absolute
        // `sys_get_temp_dir()` via a POSIX separator on Windows (phel-lang #1459).
        $bogus = 'C:\\demo\\example-app/C:\\Users\\u\\AppData\\Local\\Temp/phel/cache';
        $normalized = $this->invokeNormalize($bogus);

        self::assertStringStartsWith('C:', $normalized);
        self::assertStringNotContainsString('example-app', $normalized);
        self::assertStringNotContainsString('demo', $normalized);
    }

    public function test_normalize_preserves_windows_unc_prefix(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            self::markTestSkipped('UNC semantics only apply on Windows.');
        }

        $result = $this->invokeNormalize('\\\\server\\share\\gacela');

        self::assertStringStartsWith('\\\\', $result);
    }

    public function test_ttl_zero_means_forever(): void
    {
        $cache = new FileCache($this->cacheDir, defaultTtl: 0);
        $cache->put('key', 'eternal');

        self::assertSame('eternal', $cache->get('key'));
    }

    public function test_explicit_ttl_zero_means_forever(): void
    {
        $this->cache->put('key', 'eternal', ttl: 0);

        self::assertSame('eternal', $this->cache->get('key'));
    }

    public function test_expired_entry_returns_null(): void
    {
        $this->cache->put('short', 'lived', ttl: -1);

        self::assertNull($this->cache->get('short'));
        self::assertFalse($this->cache->has('short'));
    }

    public function test_expired_entry_is_evicted_from_disk(): void
    {
        $this->cache->put('short', 'lived', ttl: -1);

        // Force a fresh instance so the expiry path runs for the on-disk entry.
        $fresh = new FileCache($this->cacheDir);

        self::assertNull($fresh->get('short'));
        self::assertCount(0, glob($this->cacheDir . '/*.php') ?: []);
    }

    public function test_default_ttl_applied_when_put_ttl_is_null(): void
    {
        $cache = new FileCache($this->cacheDir, defaultTtl: -1);
        $cache->put('key', 'val');

        self::assertNull($cache->get('key'));
        self::assertFalse($cache->has('key'));
    }

    public function test_put_level_ttl_overrides_default(): void
    {
        $cache = new FileCache($this->cacheDir, defaultTtl: -1);
        $cache->put('key', 'val', ttl: 0);

        self::assertSame('val', $cache->get('key'));
    }

    public function test_atomic_write_leaves_no_tmp_files(): void
    {
        $this->cache->put('a', 1);
        $this->cache->put('b', 2);
        $this->cache->put('c', 3);

        $leftovers = glob($this->cacheDir . '/*.tmp') ?: [];
        self::assertCount(0, $leftovers, 'no .tmp files should remain after put');
    }

    public function test_written_file_is_always_complete_no_partial_writes(): void
    {
        // Simulate the classic "observer sees a half-written file" risk: after
        // every single put() every PHP file in the directory MUST be a fully
        // valid `<?php return [...]` document. Atomic rename guarantees this.
        for ($i = 0; $i < 10; ++$i) {
            $this->cache->put('k' . $i, ['value' => $i]);

            foreach (glob($this->cacheDir . '/*.php') ?: [] as $file) {
                $payload = require $file;
                self::assertIsArray($payload, "file {$file} must be a full array");
                self::assertArrayHasKey('value', $payload);
                self::assertArrayHasKey('expiresAt', $payload);
            }
        }
    }

    public function test_stats_returns_stats_object(): void
    {
        $stats = $this->cache->stats();

        self::assertInstanceOf(FileCacheStats::class, $stats);
        self::assertSame(0, $stats->entries);
        self::assertSame(0, $stats->bytes);
    }

    public function test_stats_counts_entries(): void
    {
        $this->cache->put('x', 1);
        $this->cache->put('y', 2);

        $stats = $this->cache->stats();

        self::assertSame(2, $stats->entries);
        self::assertGreaterThan(0, $stats->bytes);
    }

    public function test_stats_oldest_and_newest_are_null_when_empty(): void
    {
        $stats = $this->cache->stats();

        self::assertNull($stats->oldestAt);
        self::assertNull($stats->newestAt);
    }

    public function test_stats_tracks_timestamps(): void
    {
        $this->cache->put('first', 'a');
        $this->cache->put('second', 'b');

        $stats = $this->cache->stats();

        self::assertNotNull($stats->oldestAt);
        self::assertNotNull($stats->newestAt);
        self::assertGreaterThanOrEqual($stats->oldestAt, $stats->newestAt);
    }

    public function test_batch_defers_disk_write(): void
    {
        $this->cache->beginBatch();
        $this->cache->put('a', 1);
        $this->cache->put('b', 2);

        self::assertTrue($this->cache->has('a'));
        self::assertSame(1, $this->cache->get('a'));

        $cacheFiles = glob($this->cacheDir . '/*.php') ?: [];
        self::assertCount(0, $cacheFiles, 'no files written during batch');

        $this->cache->commitBatch();

        $cacheFiles = glob($this->cacheDir . '/*.php') ?: [];
        self::assertGreaterThan(0, count($cacheFiles), 'files written after commit');
    }

    public function test_batch_commit_persists_all_entries(): void
    {
        $this->cache->beginBatch();
        $this->cache->put('x', 10);
        $this->cache->put('y', 20);
        $this->cache->commitBatch();

        $fresh = new FileCache($this->cacheDir);

        self::assertSame(10, $fresh->get('x'));
        self::assertSame(20, $fresh->get('y'));
    }

    public function test_commit_without_begin_is_noop(): void
    {
        $this->cache->put('key', 'val');

        $files = glob($this->cacheDir . '/*.php') ?: [];
        self::assertGreaterThan(0, count($files));
        $sizeBefore = filesize($files[0]);

        $this->cache->commitBatch();

        clearstatcache();
        self::assertSame($sizeBefore, filesize($files[0]));
    }

    public function test_commit_with_empty_pending_writes_nothing(): void
    {
        $this->cache->beginBatch();
        $this->cache->commitBatch();

        self::assertCount(0, glob($this->cacheDir . '/*.php') ?: []);
    }

    public function test_batch_leaves_no_tmp_files(): void
    {
        $this->cache->beginBatch();
        for ($i = 0; $i < 50; ++$i) {
            $this->cache->put('key' . $i, $i);
        }

        $this->cache->commitBatch();

        $leftovers = glob($this->cacheDir . '/*.tmp') ?: [];
        self::assertCount(0, $leftovers);
    }

    public function test_write_atomically_writes_any_var_exportable_payload(): void
    {
        $path = $this->cacheDir . '/plain.php';
        FileCache::writeAtomically($path, ['a' => 1, 'b' => [2, 3]]);

        self::assertFileExists($path);
        self::assertSame(['a' => 1, 'b' => [2, 3]], require $path);
    }

    public function test_write_contents_atomically_writes_raw_content_verbatim(): void
    {
        $path = $this->cacheDir . '/report.txt';
        $content = "<?php\n// compiled\nnamespace Acme;\n";

        self::assertTrue(FileCache::writeContentsAtomically($path, $content));
        self::assertFileExists($path);
        self::assertSame($content, file_get_contents($path));
    }

    public function test_write_contents_atomically_leaves_no_tmp_files_behind(): void
    {
        FileCache::writeContentsAtomically($this->cacheDir . '/raw.php', 'hello');

        $leftovers = glob($this->cacheDir . '/*.tmp') ?: [];
        self::assertCount(0, $leftovers);
    }

    public function test_default_ttl_is_zero_so_entries_never_expire_on_disk(): void
    {
        self::assertSame(0, $this->cache->defaultTtl);

        $this->cache->put('key', 'value');

        $files = glob($this->cacheDir . '/*.php') ?: [];
        self::assertCount(1, $files);

        $entry = require $files[0];
        self::assertNull($entry['expiresAt']);
    }

    public function test_put_after_commit_batch_writes_directly_to_disk(): void
    {
        $this->cache->beginBatch();
        $this->cache->put('a', 1);
        $this->cache->commitBatch();

        $this->cache->put('b', 2);

        $fresh = new FileCache($this->cacheDir);
        self::assertSame(2, $fresh->get('b'));
    }

    public function test_commit_batch_creates_index_lock_file_inside_cache_directory(): void
    {
        $this->cache->beginBatch();
        $this->cache->put('a', 1);
        $this->cache->commitBatch();

        self::assertFileExists($this->cacheDir . DIRECTORY_SEPARATOR . '.gacela-filecache.lock');
    }

    public function test_commit_batch_falls_back_to_direct_writes_when_lock_file_unavailable(): void
    {
        // Occupying the lock path with a directory makes fopen() fail, forcing
        // the lock-less fallback, which must still persist every entry.
        mkdir($this->cacheDir . DIRECTORY_SEPARATOR . '.gacela-filecache.lock');

        $this->cache->beginBatch();
        $this->cache->put('a', 1);
        $this->cache->put('b', 2);
        $this->cache->commitBatch();

        $fresh = new FileCache($this->cacheDir);
        self::assertSame(1, $fresh->get('a'));
        self::assertSame(2, $fresh->get('b'));
    }

    public function test_stats_aggregates_bytes_and_timestamps_across_files(): void
    {
        $base = time() - 1_000;
        $files = [
            'a.php' => ['12345', $base],           // oldest
            'b.php' => ['1234567', $base + 900],   // newest
            'c.php' => ['12345678901', $base + 500],
        ];
        foreach ($files as $name => [$content, $mtime]) {
            $path = $this->cacheDir . '/' . $name;
            file_put_contents($path, $content);
            touch($path, $mtime);
        }

        $stats = $this->cache->stats();

        self::assertSame(3, $stats->entries);
        self::assertSame(5 + 7 + 11, $stats->bytes);
        self::assertSame($base, $stats->oldestAt);
        self::assertSame($base + 900, $stats->newestAt);
    }

    public function test_write_contents_atomically_stages_tmp_next_to_target_not_in_cwd(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            self::markTestSkipped('Requires a non-writable cwd, which root can always write to.');
        }

        $unwritableCwd = $this->cacheDir . '/no-write-cwd';
        mkdir($unwritableCwd, 0555);
        $cwd = (string) getcwd();
        chdir($unwritableCwd);

        try {
            $ok = FileCache::writeContentsAtomically($this->cacheDir . '/staged.php', 'payload');
        } finally {
            chdir($cwd);
            chmod($unwritableCwd, 0755);
        }

        self::assertTrue($ok);
        self::assertSame('payload', file_get_contents($this->cacheDir . '/staged.php'));
    }

    public function test_write_contents_atomically_returns_false_when_file_write_fails(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            self::markTestSkipped('Requires a non-writable directory, which root can always write to.');
        }

        // Warm the writability memo while the directory is writable, then
        // revoke write permission so the actual file write fails.
        $dir = $this->cacheDir . '/revoked';
        mkdir($dir);
        self::assertTrue(FileCache::writeContentsAtomically($dir . '/warm.php', 'warm'));
        chmod($dir, 0555);

        try {
            self::assertFalse(FileCache::writeContentsAtomically($dir . '/fail.php', 'data'));
        } finally {
            chmod($dir, 0755);
        }
    }

    public function test_write_contents_atomically_returns_false_when_rename_fails(): void
    {
        // Renaming onto an existing directory fails after the tmp stage succeeded.
        $target = $this->cacheDir . '/occupied';
        mkdir($target);

        self::assertFalse(FileCache::writeContentsAtomically($target, 'data'));
        self::assertCount(0, glob($this->cacheDir . '/*.tmp') ?: []);
    }

    public function test_expired_memory_entry_is_evicted_from_disk_too(): void
    {
        $this->cache->put('short', 'lived', ttl: -1);

        // The same instance holds the entry in memory: expiry via the memory
        // path must also remove the stale file on disk.
        self::assertNull($this->cache->get('short'));
        self::assertCount(0, glob($this->cacheDir . '/*.php') ?: []);
    }

    public function test_entry_expiring_exactly_now_is_already_expired(): void
    {
        // Retry when the clock ticks between write and read, so the
        // boundary (expiresAt === now) is what actually gets asserted.
        do {
            $now = time();
            $file = $this->cacheDir . '/' . sha1('edge') . '.php';
            file_put_contents(
                $file,
                '<?php return ' . var_export(['value' => 'v', 'expiresAt' => $now], true) . ';',
            );
            $fresh = new FileCache($this->cacheDir);
            $hasEntry = $fresh->has('edge');
        } while (time() !== $now);

        self::assertFalse($hasEntry);
    }

    public function test_normalize_keeps_single_embedded_drive_reference_intact(): void
    {
        $expected = DIRECTORY_SEPARATOR . 'foo' . DIRECTORY_SEPARATOR . 'C:' . DIRECTORY_SEPARATOR . 'bar';

        self::assertSame($expected, $this->invokeNormalize('/foo/C:/bar'));
    }

    public function test_normalize_folds_backslashes_to_directory_separator(): void
    {
        $expected = 'a' . DIRECTORY_SEPARATOR . 'b' . DIRECTORY_SEPARATOR . 'c';

        self::assertSame($expected, $this->invokeNormalize('a\\b\\c'));
    }

    public function test_normalize_trims_trailing_separators(): void
    {
        $expected = DIRECTORY_SEPARATOR . 'foo' . DIRECTORY_SEPARATOR . 'bar';

        self::assertSame($expected, $this->invokeNormalize('/foo/bar///'));
    }

    private function invokeNormalize(string $dir): string
    {
        $method = new ReflectionMethod(FileCache::class, 'normalizeDirectory');
        $instance = (new ReflectionClass(FileCache::class))->newInstanceWithoutConstructor();

        return (string) $method->invoke($instance, $dir);
    }
}
