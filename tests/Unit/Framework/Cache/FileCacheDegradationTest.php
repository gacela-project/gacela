<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Cache;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\WritableDirectory;
use GacelaTest\Fixtures\ReadOnlyDirTrait;
use GacelaTest\Fixtures\WarningCollectorTrait;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function glob;
use function sha1;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
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
