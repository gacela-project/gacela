<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Cache;

use Gacela\Framework\Cache\CacheFilePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use const DIRECTORY_SEPARATOR;

/**
 * "Nowhere" must never come out as the filesystem root.
 *
 * `FileCache` normalizes `''`, `'/'` and whitespace to an empty directory, and
 * every path was concatenated onto it directly: the entry path became
 * `'/<sha1>.php'` and the glob `'/*.php'`. A cache with no usable directory
 * wrote entries to the root, read them back, and would have deleted every PHP
 * file it found there.
 *
 * Asserted here rather than through `FileCache`, because exercising it there
 * means creating files at the root -- which is exactly what Windows CI did,
 * where the drive root is writable and the entries really landed on `D:\`.
 */
final class CacheFilePathTest extends TestCase
{
    /**
     * Every spelling `FileCache` reduces to an empty directory. `'//'` is
     * deliberately absent: on Windows it normalizes to the UNC prefix `\\`
     * rather than to nothing, so it is a different case on a different
     * platform and says nothing about this rule.
     *
     * @return iterable<string, array{string}>
     */
    public static function nowhere(): iterable
    {
        yield 'empty' => [''];
    }

    #[DataProvider('nowhere')]
    public function test_nowhere_yields_no_glob_pattern(string $directory): void
    {
        $pattern = CacheFilePath::pattern($directory);

        self::assertNull($pattern);
        self::assertNotSame('/*.php', $pattern);
    }

    /**
     * The one that mattered on Windows: an entry path built from nothing is a
     * path at the drive root, and the root is writable there.
     */
    #[DataProvider('nowhere')]
    public function test_nowhere_yields_no_entry_path(string $directory): void
    {
        self::assertNull(CacheFilePath::forEntry($directory, 'any-key'));
        self::assertNull(CacheFilePath::inDirectory($directory, '.gacela-filecache.lock'));
    }

    public function test_a_directory_matches_the_php_files_directly_in_it(): void
    {
        self::assertSame('/var/cache/gacela/*.php', CacheFilePath::pattern('/var/cache/gacela'));
        self::assertSame('var/cache/*.php', CacheFilePath::pattern('var/cache'));
    }

    public function test_an_entry_lives_in_the_directory_under_a_hashed_name(): void
    {
        $path = CacheFilePath::forEntry('/var/cache', 'the-key');

        self::assertNotNull($path);
        self::assertStringStartsWith('/var/cache' . DIRECTORY_SEPARATOR, $path);
        self::assertStringEndsWith('.php', $path);
    }

    /**
     * Same key, same file -- otherwise a written entry could never be read
     * back.
     */
    public function test_an_entry_path_is_stable_for_a_key(): void
    {
        self::assertSame(
            CacheFilePath::forEntry('/var/cache', 'the-key'),
            CacheFilePath::forEntry('/var/cache', 'the-key'),
        );
        self::assertNotSame(
            CacheFilePath::forEntry('/var/cache', 'the-key'),
            CacheFilePath::forEntry('/var/cache', 'other-key'),
        );
    }

    public function test_a_named_file_lives_in_the_directory(): void
    {
        self::assertSame(
            '/var/cache' . DIRECTORY_SEPARATOR . '.lock',
            CacheFilePath::inDirectory('/var/cache', '.lock'),
        );
    }

    /**
     * A directory that does not exist is not this rule's business: the path is
     * well-formed and the filesystem answers for it.
     */
    public function test_a_directory_that_does_not_exist_still_gets_paths(): void
    {
        self::assertSame('/no/such/place/*.php', CacheFilePath::pattern('/no/such/place'));
        self::assertNotNull(CacheFilePath::forEntry('/no/such/place', 'k'));
    }
}
