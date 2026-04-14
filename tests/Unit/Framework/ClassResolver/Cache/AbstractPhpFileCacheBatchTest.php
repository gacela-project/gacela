<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Cache;

use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function glob;
use function is_dir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class AbstractPhpFileCacheBatchTest extends TestCase
{
    private string $cacheDir;

    /** @var list<string> */
    private array $blockerFiles = [];

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

        foreach ($this->blockerFiles as $blocker) {
            if (is_file($blocker)) {
                unlink($blocker);
            }
        }

        $this->blockerFiles = [];
    }

    public function test_is_batching_reflects_current_batch_state(): void
    {
        self::assertFalse(AbstractPhpFileCache::isBatching());

        AbstractPhpFileCache::beginBatch();
        self::assertTrue(AbstractPhpFileCache::isBatching());

        AbstractPhpFileCache::commitBatch();
        self::assertFalse(AbstractPhpFileCache::isBatching());
    }

    public function test_put_overwrites_existing_key_with_different_value(): void
    {
        $cache = new TestPhpFileCache($this->cacheDir);

        $cache->put('key', 'Original');
        $cache->put('key', 'Updated');

        self::assertSame('Updated', $cache->get('key'));
        self::assertSame(['key' => 'Updated'], require $this->cacheFile());
    }

    public function test_put_with_identical_value_does_not_rewrite_the_file(): void
    {
        $cache = new TestPhpFileCache($this->cacheDir);
        $cache->put('key', 'A');

        $firstContent = file_get_contents($this->cacheFile());
        $firstMtime = filemtime($this->cacheFile());

        // Force a detectable mtime gap on systems with second-resolution filesystems.
        usleep(1_100_000);
        clearstatcache(true, $this->cacheFile());

        $cache->put('key', 'A');

        clearstatcache(true, $this->cacheFile());
        self::assertSame($firstContent, file_get_contents($this->cacheFile()));
        self::assertSame($firstMtime, filemtime($this->cacheFile()));
    }

    public function test_constructor_loads_existing_cache_entries_from_disk(): void
    {
        $cache1 = new TestPhpFileCache($this->cacheDir);
        $cache1->put('persisted', 'ClassP');
        TestPhpFileCache::clearStaticCache();

        $cache2 = new TestPhpFileCache($this->cacheDir);

        self::assertSame(['persisted' => 'ClassP'], $cache2->getAll());
    }

    public function test_constructor_loads_every_persisted_entry_not_just_the_first(): void
    {
        $cache1 = new TestPhpFileCache($this->cacheDir);
        $cache1->put('first', 'ClassOne');
        $cache1->put('second', 'ClassTwo');
        $cache1->put('third', 'ClassThree');
        TestPhpFileCache::clearStaticCache();

        $cache2 = new TestPhpFileCache($this->cacheDir);

        self::assertSame(
            ['first' => 'ClassOne', 'second' => 'ClassTwo', 'third' => 'ClassThree'],
            $cache2->getAll(),
        );
    }

    public function test_constructor_throws_when_cache_directory_cannot_be_created(): void
    {
        $blocker = sys_get_temp_dir() . '/gacela-blocker-' . uniqid('', true);
        file_put_contents($blocker, 'blocked');
        $this->blockerFiles[] = $blocker;

        // mkdir() emits an E_WARNING when the parent exists as a file; PHPUnit
        // turns that into a test warning. Suppress it so only the thrown
        // RuntimeException reaches the assertions.
        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('was not created');

            new TestPhpFileCache($blocker . '/subdir');
        } finally {
            restore_error_handler();
        }
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

    public function test_put_still_works_after_clear_static_cache_on_existing_instance(): void
    {
        $cache = new TestPhpFileCache($this->cacheDir);

        TestPhpFileCache::clearStaticCache();

        $cache->put('survivor', 'ClassZ');

        self::assertFileExists($this->cacheFile());
        self::assertSame(['survivor' => 'ClassZ'], require $this->cacheFile());
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

        if ((glob($dir . '/*') ?: []) === []) {
            rmdir($dir);
        }
    }
}
