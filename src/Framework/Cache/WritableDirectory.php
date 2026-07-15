<?php

declare(strict_types=1);

namespace Gacela\Framework\Cache;

use function is_dir;
use function is_writable;
use function mkdir;

/**
 * Answers whether a directory can hold cache files, creating it when missing.
 * The verdict is memoized per directory for the process lifetime.
 */
final class WritableDirectory
{
    /** @var array<string,bool> */
    private static array $usableByDir = [];

    public static function isUsable(string $directory): bool
    {
        return self::$usableByDir[$directory] ??= self::probe($directory);
    }

    /**
     * @internal
     */
    public static function resetCache(): void
    {
        self::$usableByDir = [];
    }

    private static function probe(string $directory): bool
    {
        if (!is_dir($directory) && !@mkdir($directory, recursive: true) && !is_dir($directory)) {
            return false;
        }

        return is_writable($directory);
    }
}
