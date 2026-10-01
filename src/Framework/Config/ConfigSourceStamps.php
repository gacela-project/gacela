<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use function clearstatcache;
use function explode;
use function implode;
use function stat;

/**
 * What a merged config was read from, as cheap facts about each path: enough to
 * tell, without globbing, that a file was edited, added or removed since.
 *
 * Inode as well as mtime and size: editors save by writing a new file and
 * renaming it over the old one, so an edit within the same second that keeps
 * the size still changes the inode.
 *
 * @internal
 */
final class ConfigSourceStamps
{
    /**
     * @param list<string> $paths files and directories; a missing path is stamped too
     *
     * @return array<string,string>
     */
    public static function of(array $paths): array
    {
        $stamps = [];

        foreach ($paths as $path) {
            $stamps[$path] = self::stampOf($path);
        }

        return $stamps;
    }

    /**
     * @param array<string,string> $stamps
     */
    public static function areCurrent(array $stamps): bool
    {
        // PHP remembers the last stat it made. A long-running process, or a
        // second bootstrap in one, would otherwise read the answer from before
        // the edit. Without `true`, the realpath cache is left alone.
        clearstatcache();

        foreach ($stamps as $path => $stamp) {
            if (self::stampOf($path) !== $stamp) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a stamp could still miss a change. `stat()` gives whole seconds,
     * and on most filesystems a directory keeps its size when a file is added,
     * so a change in the same second as the stamp leaves it equal. A path
     * touched in the current second is that case; the way git treats a
     * "racily clean" index entry, the answer is not to trust the stamp yet.
     *
     * @param array<string,string> $stamps
     */
    public static function couldMissAChange(array $stamps, int $now): bool
    {
        foreach ($stamps as $stamp) {
            if ($stamp !== '' && (int) $stamp >= $now) {
                return true;
            }
        }

        return false;
    }

    /**
     * One string rather than an array: the cache file is compiled on every
     * request where OPcache is off, as in most CLI runs, and a string literal
     * compiles in a fraction of the time an array of them does. Tab and
     * newline separate, because `var_export()` writes both literally, while
     * a NUL byte would become a concatenation to compile.
     *
     * @param array<string,string> $stamps
     */
    public static function pack(array $stamps): string
    {
        $lines = [];

        foreach ($stamps as $path => $stamp) {
            $lines[] = $path . "\t" . $stamp;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string,string>
     */
    public static function unpack(string $packed): array
    {
        $stamps = [];

        foreach (explode("\n", $packed) as $line) {
            [$path, $stamp] = explode("\t", $line, 2) + [1 => ''];
            $stamps[$path] = $stamp;
        }

        return $stamps;
    }

    private static function stampOf(string $path): string
    {
        $stat = @stat($path);

        if ($stat === false) {
            return '';
        }

        return implode(':', [$stat['mtime'], $stat['size'], $stat['ino']]);
    }
}
