<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures;

use function array_diff;
use function chmod;
use function is_dir;
use function is_writable;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Read-only directory scenarios for cache-degradation tests.
 *
 * Call {@see restoreReadOnlyDirs()} from tearDown().
 */
trait ReadOnlyDirTrait
{
    /** @var list<string> */
    private array $readOnlyDirs = [];

    /**
     * A fresh temp directory chmod'ed to 0555. Skips the test when the
     * environment cannot produce an unwritable directory (running as root,
     * or a filesystem that ignores permissions, e.g. on Windows).
     *
     * @param callable(string):void|null $seed populate the directory before it turns read-only
     */
    private function createReadOnlyDirOrSkip(string $prefix, ?callable $seed = null): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-' . $prefix . '-' . uniqid('', true);
        mkdir($dir, 0o755, true);
        $this->readOnlyDirs[] = $dir;

        if ($seed !== null) {
            $seed($dir);
        }

        chmod($dir, 0o555);

        if (is_writable($dir)) {
            self::markTestSkipped('chmod(0555) does not make the directory unwritable in this environment');
        }

        return $dir;
    }

    private function restoreReadOnlyDirs(): void
    {
        foreach ($this->readOnlyDirs as $dir) {
            $this->removeRestoringPermissions($dir);
        }

        $this->readOnlyDirs = [];
    }

    private function removeRestoringPermissions(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);

            return;
        }

        @chmod($path, 0o755);

        // scandir instead of glob: dot-prefixed children (e.g. `.gacela`)
        // must be removed too.
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $child) {
            $this->removeRestoringPermissions($path . DIRECTORY_SEPARATOR . $child);
        }

        @rmdir($path);
    }
}
