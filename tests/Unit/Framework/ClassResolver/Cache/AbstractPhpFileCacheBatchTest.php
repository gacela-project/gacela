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
        ClassNamePhpCache::clearStaticCache();
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

        AbstractPhpFileCache::beginBatch();
        $cache->put('key1', 'ClassA');
        $cache->put('key2', 'ClassB');

        self::assertFileDoesNotExist($this->cacheFile());
        self::assertTrue($cache->has('key1'));
        self::assertSame('ClassA', $cache->get('key1'));

        AbstractPhpFileCache::commitBatch();

        self::assertFileExists($this->cacheFile());
        $entries = require $this->cacheFile();
        self::assertSame(['key1' => 'ClassA', 'key2' => 'ClassB'], $entries);
    }

    public function test_commit_without_begin_is_a_noop(): void
    {
        $cache = new TestPhpFileCache($this->cacheDir);
        $cache->put('key1', 'ClassA');
        $contentBefore = file_get_contents($this->cacheFile());

        AbstractPhpFileCache::commitBatch();

        self::assertSame($contentBefore, file_get_contents($this->cacheFile()));
    }

    public function test_batch_flush_leaves_no_tmp_files_behind(): void
    {
        $cache = new TestPhpFileCache($this->cacheDir);

        AbstractPhpFileCache::beginBatch();
        for ($i = 0; $i < 50; ++$i) {
            $cache->put('key' . $i, 'ClassX');
        }
        AbstractPhpFileCache::commitBatch();

        $leftovers = glob($this->cacheDir . '/*.tmp') ?: [];
        self::assertCount(0, $leftovers, 'atomic rename must clean up .tmp stage files');
    }

    public function test_commit_flushes_every_dirty_concrete_cache(): void
    {
        $testCache = new TestPhpFileCache($this->cacheDir);
        $classNameCache = new ClassNamePhpCache($this->cacheDir);

        AbstractPhpFileCache::beginBatch();
        $testCache->put('a', 'A');
        $classNameCache->put('b', 'B');

        self::assertFileDoesNotExist($this->cacheFile());
        self::assertFileDoesNotExist($this->classNameFile());

        AbstractPhpFileCache::commitBatch();

        self::assertFileExists($this->cacheFile());
        self::assertFileExists($this->classNameFile());
    }

    public function test_commit_skips_concrete_caches_without_puts(): void
    {
        new TestPhpFileCache($this->cacheDir);
        new ClassNamePhpCache($this->cacheDir);

        AbstractPhpFileCache::beginBatch();
        AbstractPhpFileCache::commitBatch();

        self::assertFileDoesNotExist($this->cacheFile());
        self::assertFileDoesNotExist($this->classNameFile());
    }

    private function cacheFile(): string
    {
        return $this->cacheDir . '/' . TestPhpFileCache::FILENAME;
    }

    private function classNameFile(): string
    {
        return $this->cacheDir . '/' . ClassNamePhpCache::FILENAME;
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
