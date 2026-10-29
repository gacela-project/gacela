<?php

declare(strict_types=1);

namespace Gacela\Framework\Cache;

use function sha1;

use const DIRECTORY_SEPARATOR;

final class CacheFilePath
{
    private const ENTRY_PREFIX = 'gacela-fc-';

    /**
     * The glob matching every entry file, or null when there is nowhere to look.
     */
    public static function pattern(string $directory): ?string
    {
        if ($directory === '') {
            return null;
        }

        return $directory . '/' . self::ENTRY_PREFIX . '*.php';
    }

    /**
     * Where one entry lives, or null when there is nowhere to put it.
     */
    public static function forEntry(string $directory, string $key): ?string
    {
        return self::inDirectory($directory, self::ENTRY_PREFIX . sha1($key) . '.php');
    }

    public static function legacyPattern(string $directory): ?string
    {
        if ($directory === '') {
            return null;
        }

        return $directory . '/' . str_repeat('[0-9a-fA-F]', 40) . '.php';
    }

    public static function forLegacyEntry(string $directory, string $key): ?string
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
