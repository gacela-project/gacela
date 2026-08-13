<?php

declare(strict_types=1);

namespace Gacela\Framework\Cache;

use function sha1;

use const DIRECTORY_SEPARATOR;

/**
 * Where a cache directory's files live, or nothing when there is no directory.
 *
 * {@see FileCache::normalizeDirectory()} reduces `''`, `'/'` and whitespace to
 * an empty string, and every path this class used to build was concatenated
 * onto it directly. That made "nowhere" indistinguishable from the filesystem
 * root: the entry path became `'/<sha1>.php'` and the glob `'/*.php'`, so a
 * cache with no usable directory would write entries to the root, read them
 * back, and delete every PHP file it found there.
 *
 * Not merely theoretical -- on Windows the drive root is writable, and a cache
 * constructed with an empty directory really did persist entries to `D:\`.
 *
 * A rule of its own because that case cannot be exercised through `FileCache`
 * without creating files at the root. Here the path is the return value, so
 * "nowhere" is asserted directly.
 */
final class CacheFilePath
{
    /**
     * The glob matching every entry file, or null when there is nowhere to look.
     */
    public static function pattern(string $directory): ?string
    {
        if ($directory === '') {
            return null;
        }

        return $directory . '/*.php';
    }

    /**
     * Where one entry lives, or null when there is nowhere to put it.
     */
    public static function forEntry(string $directory, string $key): ?string
    {
        return self::inDirectory($directory, sha1($key) . '.php');
    }

    /**
     * Where a named file in the cache directory lives, or null when there is
     * no directory to hold it.
     *
     * A directory that merely does not exist is not this case: the path is
     * well-formed and the filesystem answers for it.
     */
    public static function inDirectory(string $directory, string $filename): ?string
    {
        if ($directory === '') {
            return null;
        }

        return $directory . DIRECTORY_SEPARATOR . $filename;
    }
}
