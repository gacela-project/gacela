<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures;

use function in_array;
use function stream_get_wrappers;
use function stream_wrapper_register;
use function stream_wrapper_unregister;

/**
 * A filesystem where the directory is writable, writing a file silently
 * produces zero bytes, and deleting it fails -- the shape a full disk or a
 * stale network mount has, and the only way to reach the staging-failure
 * branch of {@see \Gacela\Framework\Cache\FileCache::writeContentsAtomically}
 * in-process.
 *
 * rename() is deliberately permissive so that promoting the failed stage file
 * would succeed, making a missing failure guard observable.
 */
final class FailingWriteStreamWrapper
{
    public const PROTOCOL = 'gacelafailingwrite';

    public const DIRECTORY = self::PROTOCOL . '://cache';

    public static bool $renamed = false;

    /** @var resource|null */
    public $context;

    public static function register(): void
    {
        self::$renamed = false;

        if (in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::PROTOCOL);
        }

        stream_wrapper_register(self::PROTOCOL, self::class);
    }

    public static function unregister(): void
    {
        stream_wrapper_unregister(self::PROTOCOL);
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        return 0;
    }

    public function stream_flush(): bool
    {
        return true;
    }

    public function stream_lock(int $operation): bool
    {
        return true;
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return true;
    }

    public function stream_close(): void
    {
    }

    public function unlink(string $path): bool
    {
        return false;
    }

    public function rename(string $from, string $to): bool
    {
        self::$renamed = true;

        return true;
    }

    /**
     * @return array<string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        if ($path !== self::DIRECTORY) {
            return false;
        }

        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0o040777,
            'nlink' => 0,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => 0,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => -1,
            'blocks' => -1,
        ];
    }
}
