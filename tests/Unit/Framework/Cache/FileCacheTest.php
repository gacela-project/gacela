<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Cache;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\FileCacheStats;
use PHPUnit\Framework\TestCase;

use function count;
use function filesize;
use function glob;
use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

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
        $this->removeDir($this->cacheDir);
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
        $this->removeDir($dir);
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

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = glob($dir . '/*') ?: [];
        foreach ($entries as $entry) {
            if (is_dir($entry)) {
                $this->removeDir($entry);
            } else {
                unlink($entry);
            }
        }

        // Also clean up dotfiles like the batch lockfile.
        foreach (glob($dir . '/.[!.]*') ?: [] as $dotfile) {
            if (is_dir($dotfile)) {
                $this->removeDir($dotfile);
            } else {
                unlink($dotfile);
            }
        }

        rmdir($dir);
    }
}
