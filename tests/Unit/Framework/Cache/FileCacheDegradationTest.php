<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Cache;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\WritableDirectory;
use GacelaTest\Fixtures\FailingWriteStreamWrapper;
use GacelaTest\Fixtures\ReadOnlyDirTrait;
use GacelaTest\Fixtures\WarningCollectorTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function mkdir;
use function rmdir;
use function sha1;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

final class FileCacheDegradationTest extends TestCase
{
    use ReadOnlyDirTrait;
    use WarningCollectorTrait;

    protected function setUp(): void
    {
        WritableDirectory::resetCache();
    }

    protected function tearDown(): void
    {
        WritableDirectory::resetCache();
        $this->restoreReadOnlyDirs();
    }

    /**
     * Every spelling of "nowhere" normalizes to an empty directory.
     *
     * This is what made the unguarded glob reachable: `$this->directory . '/*.php'`
     * becomes `'/*.php'`, so {@see FileCache::stats()} would report every PHP
     * file at the filesystem root as a cache entry and {@see FileCache::clear()}
     * would unlink them -- from a cache that, having no usable directory, never
     * wrote a file and holds nothing on disk to clear.
     *
     * `'//'` is deliberately absent: on Windows it normalizes to the UNC
     * prefix `\\` rather than to nothing, so it is a different case on a
     * different platform.
     *
     * @return iterable<string, array{string}>
     */
    public static function nowhereDirectories(): iterable
    {
        yield 'empty' => [''];
        yield 'root' => ['/'];
        yield 'whitespace' => ['   '];
        yield 'trailing separator only' => ['/ '];
    }

    #[DataProvider('nowhereDirectories')]
    public function test_a_directory_that_means_nowhere_normalizes_to_empty(string $directory): void
    {
        self::assertSame('', (new FileCache($directory))->directory);
        self::assertFalse((new FileCache($directory))->isPersistent());
    }

    /**
     * Nothing on disk belongs to a cache with nowhere to put it, so there is
     * nothing to count and nothing to delete.
     */
    #[DataProvider('nowhereDirectories')]
    public function test_a_cache_with_nowhere_to_write_owns_no_files(string $directory): void
    {
        $fileCache = new FileCache($directory);

        self::assertSame(0, $fileCache->stats()->entries);
        self::assertSame(0, $fileCache->stats()->bytes);

        $fileCache->clear();

        self::assertSame(0, $fileCache->stats()->entries);
    }

    /**
     * Nothing reaches the disk at all -- which on Windows it did: the entry
     * path built from an empty directory is a path at the drive root, and the
     * drive root is writable there, so entries really were persisted to `D:\`
     * and read back after a clear().
     */
    public function test_a_cache_with_nowhere_to_write_persists_nothing(): void
    {
        $fileCache = new FileCache('');
        $fileCache->put('key', 'value');

        // A second cache shares no memory with the first, so anything it can
        // still see came off the disk.
        self::assertNull((new FileCache(''))->get('key'));
    }

    /**
     * The batch path too: committing takes an index-file lock, and there is no
     * index file when there is no directory to hold one.
     */
    public function test_committing_a_batch_with_nowhere_to_write_persists_nothing(): void
    {
        $fileCache = new FileCache('');
        $fileCache->beginBatch();
        $fileCache->put('key', 'value');
        $fileCache->commitBatch();

        self::assertSame('value', $fileCache->get('key'));
        self::assertNull((new FileCache(''))->get('key'));
        self::assertSame(0, $fileCache->stats()->entries);
    }

    /**
     * In memory, though, it still works -- that is the degradation the class
     * documents, and clearing has to reach it.
     */
    public function test_clearing_a_cache_with_nowhere_to_write_still_empties_memory(): void
    {
        $fileCache = new FileCache('');
        $fileCache->put('key', 'value');

        self::assertSame('value', $fileCache->get('key'));

        $fileCache->clear();

        self::assertNull($fileCache->get('key'));
    }

    /**
     * The guard must not cost a real directory anything: its files are still
     * counted and still deleted.
     */
    public function test_a_usable_directory_is_still_counted_and_cleared(): void
    {
        $directory = sys_get_temp_dir() . '/gacela-cache-guard-' . uniqid();
        mkdir($directory, recursive: true);

        $fileCache = new FileCache($directory);
        $fileCache->put('key', 'value');

        self::assertSame(1, $fileCache->stats()->entries);

        $fileCache->clear();

        self::assertSame(0, $fileCache->stats()->entries);
        self::assertSame([], glob($directory . '/*.php') ?: []);

        rmdir($directory);
    }

    public function test_uncreatable_directory_degrades_to_memory_only(): void
    {
        $dir = $this->uncreatableDir();

        $warnings = $this->collectWarnings(static function () use ($dir): FileCache {
            /** @var FileCache<string> $cache */
            $cache = new FileCache($dir);
            $cache->put('key', 'value');

            return $cache;
        }, $cache);

        self::assertSame([], $warnings);
        self::assertFalse($cache->isPersistent());
        self::assertTrue($cache->has('key'));
        self::assertSame('value', $cache->get('key'));
        self::assertDirectoryDoesNotExist($dir);
    }

    public function test_is_persistent_when_directory_is_writable(): void
    {
        /** @var FileCache<string> $cache */
        $cache = new FileCache($this->writableDir());

        self::assertTrue($cache->isPersistent());
    }

    public function test_reads_pre_warmed_entries_from_read_only_directory(): void
    {
        $dir = $this->createReadOnlyDirOrSkip('filecache-readonly', static function (string $dir): void {
            self::seedEntryFile($dir, 'warm', 'from-disk');
        });

        /** @var FileCache<string> $cache */
        $cache = new FileCache($dir);

        self::assertFalse($cache->isPersistent());
        self::assertSame('from-disk', $cache->get('warm'));
    }

    public function test_put_in_read_only_directory_updates_memory_without_warnings(): void
    {
        $dir = $this->createReadOnlyDirOrSkip('filecache-readonly-put', static function (string $dir): void {
            self::seedEntryFile($dir, 'warm', 'from-disk');
        });

        /** @var FileCache<string> $cache */
        $cache = new FileCache($dir);

        $warnings = $this->collectWarnings(static function () use ($cache): void {
            $cache->put('warm', 'updated');
            $cache->put('fresh', 'memory-only');
        });

        self::assertSame([], $warnings);
        self::assertSame('updated', $cache->get('warm'));
        self::assertSame('memory-only', $cache->get('fresh'));
        self::assertCount(1, glob($dir . '/*.php') ?: [], 'the read-only directory must stay untouched');
    }

    public function test_commit_batch_in_unusable_directory_keeps_entries_in_memory(): void
    {
        $dir = $this->uncreatableDir();
        /** @var FileCache<string> $cache */
        $cache = new FileCache($dir);

        $warnings = $this->collectWarnings(static function () use ($cache): void {
            $cache->beginBatch();
            $cache->put('a', 'A');
            $cache->put('b', 'B');
            $cache->commitBatch();
        });

        self::assertSame([], $warnings);
        self::assertSame('A', $cache->get('a'));
        self::assertSame('B', $cache->get('b'));
        self::assertDirectoryDoesNotExist($dir);
    }

    public function test_write_atomically_returns_false_for_unusable_directory(): void
    {
        $dir = $this->uncreatableDir();

        $warnings = $this->collectWarnings(
            static fn (): bool => FileCache::writeAtomically($dir . '/entry.php', ['k' => 'v']),
            $written,
        );

        self::assertSame([], $warnings);
        self::assertFalse($written);
    }

    public function test_write_atomically_returns_true_on_success(): void
    {
        $file = $this->writableDir() . '/entry.php';

        self::assertTrue(FileCache::writeAtomically($file, ['k' => 'v']));
        self::assertSame(['k' => 'v'], require $file);
    }

    public function test_write_contents_atomically_returns_false_for_unusable_directory(): void
    {
        $dir = $this->uncreatableDir();

        $warnings = $this->collectWarnings(
            static fn (): bool => FileCache::writeContentsAtomically($dir . '/raw.php', 'content'),
            $written,
        );

        self::assertSame([], $warnings);
        self::assertFalse($written);
    }

    public function test_write_contents_atomically_honours_the_memoized_unusable_verdict(): void
    {
        $dir = $this->uncreatableDir('memoized-verdict');
        self::assertFalse(WritableDirectory::isUsable($dir));

        // The blocker disappears and the directory shows up, as a concurrent
        // process could make happen; the verdict stands for the whole process.
        unlink(dirname($dir));
        mkdir($dir, 0o777, true);

        self::assertFalse(FileCache::writeContentsAtomically($dir . '/raw.php', 'content'));
        self::assertFileDoesNotExist($dir . '/raw.php');
    }

    public function test_write_contents_atomically_does_not_promote_a_stage_file_that_failed_to_write(): void
    {
        FailingWriteStreamWrapper::register();

        try {
            $written = FileCache::writeContentsAtomically(
                FailingWriteStreamWrapper::DIRECTORY . '/raw.php',
                'content',
            );
        } finally {
            FailingWriteStreamWrapper::unregister();
        }

        self::assertFalse($written);
        self::assertFalse(FailingWriteStreamWrapper::$renamed, 'a half-written stage file must never reach the target');
    }

    public function test_write_contents_atomically_returns_true_on_success(): void
    {
        $file = $this->writableDir() . '/raw.php';

        self::assertTrue(FileCache::writeContentsAtomically($file, 'raw content'));
        self::assertSame('raw content', file_get_contents($file));
    }

    private function writableDir(): string
    {
        $dir = sys_get_temp_dir() . '/gacela-degradation-' . uniqid('', true);
        $this->readOnlyDirs[] = $dir;

        return $dir;
    }

    private static function seedEntryFile(string $dir, string $key, string $value): void
    {
        $entry = ['value' => $value, 'expiresAt' => null];
        file_put_contents(
            $dir . '/' . sha1($key) . '.php',
            sprintf('<?php return %s;', var_export($entry, true)),
        );
    }
}
