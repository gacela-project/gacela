<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Cache;

use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use PHPUnit\Framework\TestCase;

use function count;
use function glob;
use function is_dir;
use function sys_get_temp_dir;
use function uniqid;

final class AbstractPhpFileCacheBatchTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/gacela-cache-batch-' . uniqid('', true);
        TestPhpFileCache::clearStaticCache();
    }

    protected function tearDown(): void
    {
        TestPhpFileCache::clearStaticCache();
        ClassNamePhpCache::clearStaticCache();
        $this->removeDir($this->cacheDir);
    }

    public function test_put_outside_batch_writes_immediately(): void
    {
        $cache = new TestPhpFileCache($this->cacheDir);

        $cache->put('key1', 'ClassA');

        self::assertFileExists($this->cacheFile());
        $entries = require $this->cacheFile();
        self::assertSame(['key1' => 'ClassA'], $entries);
    }

    public function test_put_inside_batch_defers_disk_write(): void
    {
        $cache = new TestPhpFileCache($this->cacheDir);

        TestPhpFileCache::beginBatch();
        $cache->put('key1', 'ClassA');
        $cache->put('key2', 'ClassB');

        self::assertFileDoesNotExist($this->cacheFile());
        self::assertTrue($cache->has('key1'));
        self::assertSame('ClassA', $cache->get('key1'));

        TestPhpFileCache::commitBatch();

        self::assertFileExists($this->cacheFile());
        $entries = require $this->cacheFile();
        self::assertSame(['key1' => 'ClassA', 'key2' => 'ClassB'], $entries);
    }

    public function test_commit_without_begin_is_a_noop(): void
    {
        $cache = new TestPhpFileCache($this->cacheDir);
        $cache->put('key1', 'ClassA');
        $before = filemtime($this->cacheFile());

        usleep(1_100_000);
        TestPhpFileCache::commitBatch();

        self::assertSame($before, filemtime($this->cacheFile()));
    }

    public function test_batch_flush_leaves_no_tmp_files_behind(): void
    {
        $cache = new TestPhpFileCache($this->cacheDir);

        TestPhpFileCache::beginBatch();
        for ($i = 0; $i < 50; ++$i) {
            $cache->put('key' . $i, 'ClassX');
        }
        TestPhpFileCache::commitBatch();

        $leftovers = glob($this->cacheDir . '/*.tmp') ?: [];
        self::assertCount(0, $leftovers, 'atomic rename must clean up .tmp stage files');
    }

    public function test_batch_isolation_per_concrete_class(): void
    {
        // ClassNamePhpCache batch is separate from TestPhpFileCache batch.
        $cacheA = new TestPhpFileCache($this->cacheDir);
        new ClassNamePhpCache($this->cacheDir);

        TestPhpFileCache::beginBatch();
        self::assertTrue(TestPhpFileCache::isBatching());
        self::assertFalse(ClassNamePhpCache::isBatching());

        $cacheA->put('a', 'A');
        self::assertFileDoesNotExist($this->cacheFile());

        TestPhpFileCache::commitBatch();
        self::assertFileExists($this->cacheFile());
    }

    private function cacheFile(): string
    {
        return $this->cacheDir . '/' . TestPhpFileCache::FILENAME;
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

        if (count(glob($dir . '/*') ?: []) === 0) {
            rmdir($dir);
        }
    }
}

final class TestPhpFileCache extends AbstractPhpFileCache
{
    public const FILENAME = 'gacela-batch-test.php';

    protected function getCacheFilename(): string
    {
        return self::FILENAME;
    }
}
