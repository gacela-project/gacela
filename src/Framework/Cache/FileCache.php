<?php

declare(strict_types=1);

namespace Gacela\Framework\Cache;

use function bin2hex;
use function count;
use function dirname;
use function fclose;
use function file_put_contents;
use function filemtime;
use function filesize;
use function flock;
use function fopen;
use function function_exists;
use function glob;
use function is_file;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function random_bytes;
use function rename;
use function rtrim;
use function sha1;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function substr;
use function time;
use function trim;
use function unlink;
use function var_export;

use const DIRECTORY_SEPARATOR;
use const LOCK_EX;
use const LOCK_UN;
use const PREG_OFFSET_CAPTURE;

/**
 * Small, typed, file-backed cache primitive.
 *
 * Fixed behaviour (intentionally NOT pluggable in v1):
 *   - Serialization: {@see var_export}. Entries must be `var_export`-safe.
 *   - Atomic write: stage to a sibling `.tmp`, then {@see rename}.
 *   - Key hashing: {@see sha1}.
 *   - Eviction: none. Entries live until TTL expiry or explicit {@see clear}.
 *   - Concurrency: per-file atomic rename + an index-file `flock` for batch commits.
 *   - Opcode cache: {@see opcache_invalidate} invoked on write when available.
 *   - Degradation: an unusable cache directory disables persistence instead of
 *     throwing — entries then live only in this instance's memory (see
 *     {@see isPersistent}), and reads from a pre-warmed read-only directory
 *     keep working.
 *
 * @template T
 */
final class FileCache
{
    private const INDEX_FILENAME = '.gacela-filecache.lock';

    public readonly string $directory;

    /** @var array<string, array{value: T, expiresAt: int|null}> */
    private array $memory = [];

    private bool $batching = false;

    /** @var array<string, array{value: T, expiresAt: int|null}> */
    private array $batchPending = [];

    public function __construct(
        string $directory,
        public readonly int $defaultTtl = 0,
    ) {
        $this->directory = $this->normalizeDirectory($directory);
        // Eager probe keeps the historical post-condition "directory exists
        // after construction" whenever the filesystem allows it.
        WritableDirectory::isUsable($this->directory);
    }

    /**
     * False when the cache directory is unusable: entries then live only in
     * this instance's memory and are lost when the process ends.
     */
    public function isPersistent(): bool
    {
        return WritableDirectory::isUsable($this->directory);
    }

    /**
     * @return T|null
     */
    public function get(string $key): mixed
    {
        $entry = $this->loadEntry($key);
        if ($entry === null) {
            return null;
        }

        return $entry['value'];
    }

    /**
     * @param T $value
     */
    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        $effectiveTtl = $ttl ?? $this->defaultTtl;
        $expiresAt = $effectiveTtl !== 0 ? time() + $effectiveTtl : null;

        /** @var array{value: T, expiresAt: int|null} $entry */
        $entry = ['value' => $value, 'expiresAt' => $expiresAt];

        $this->memory[$key] = $entry;

        if ($this->batching) {
            $this->batchPending[$key] = $entry;

            return;
        }

        $this->writeEntryToDisk($key, $entry);
    }

    public function has(string $key): bool
    {
        return $this->loadEntry($key) !== null;
    }

    public function forget(string $key): void
    {
        unset($this->memory[$key], $this->batchPending[$key]);
        $this->deleteEntryFile($key);
    }

    public function clear(): void
    {
        $this->memory = [];
        $this->batchPending = [];

        $files = glob($this->directory . '/*.php') ?: [];
        foreach ($files as $file) {
            // Suppressed: entries in a read-only directory cannot be removed
            // from disk; the in-memory eviction above already took effect.
            @unlink($file);
            self::invalidateOpcacheFor($file);
        }
    }

    public function beginBatch(): void
    {
        $this->batching = true;
    }

    /**
     * Flush the in-memory batch to disk under an index-level {@see flock}, so
     * concurrent commit-batches serialize instead of racing each other's writes.
     */
    public function commitBatch(): void
    {
        if (!$this->batching) {
            return;
        }

        $this->batching = false;

        if ($this->batchPending === []) {
            return;
        }

        $pending = $this->batchPending;
        $this->batchPending = [];

        $indexPath = $this->directory . DIRECTORY_SEPARATOR . self::INDEX_FILENAME;
        // Suppressed: in an unusable directory the lock file cannot exist and
        // the per-entry writes below degrade to no-ops on their own.
        $handle = @fopen($indexPath, 'c');

        if ($handle === false) {
            foreach ($pending as $key => $entry) {
                $this->writeEntryToDisk($key, $entry);
            }

            return;
        }

        flock($handle, LOCK_EX);

        try {
            foreach ($pending as $key => $entry) {
                $this->writeEntryToDisk($key, $entry);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function stats(): FileCacheStats
    {
        $files = glob($this->directory . '/*.php') ?: [];
        $bytes = 0;
        $oldestAt = null;
        $newestAt = null;

        foreach ($files as $file) {
            $bytes += (int) filesize($file);
            $mtime = (int) filemtime($file);

            if ($oldestAt === null || $mtime < $oldestAt) {
                $oldestAt = $mtime;
            }

            if ($newestAt === null || $mtime > $newestAt) {
                $newestAt = $mtime;
            }
        }

        return new FileCacheStats(
            entries: count($files),
            bytes: $bytes,
            oldestAt: $oldestAt,
            newestAt: $newestAt,
        );
    }

    /**
     * Atomically write a PHP-returning file: stage to a sibling `.tmp`, then
     * {@see rename}. `rename()` is atomic on POSIX filesystems, so readers
     * never observe a half-written payload. `opcache_invalidate()` is called
     * when available so readers see fresh bytes even under a warm opcode cache.
     *
     * Persistence is an optimization: when the target directory is unusable
     * (read-only filesystem, unwritable parent) or staging fails, this returns
     * false instead of throwing or emitting warnings, so cache writes on the
     * bootstrap path can never fatal the application.
     *
     * Exposed so other file-backed caches in the framework (e.g.
     * {@see \Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache}) can
     * share exactly one write path.
     *
     * @param mixed $value any `var_export`-safe payload
     *
     * @return bool whether the file was written
     */
    public static function writeAtomically(string $file, mixed $value): bool
    {
        if (!WritableDirectory::isUsable(dirname($file))) {
            return false;
        }

        $content = sprintf('<?php return %s;', var_export($value, true));
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        // Suppressed: a failed write degrades to "not persisted", not a warning.
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }

        if (!@rename($tmp, $file)) {
            @unlink($tmp);

            return false;
        }

        self::invalidateOpcacheFor($file);

        return true;
    }

    /**
     * Resolve an entry from memory or disk, transparently evicting anything past TTL.
     *
     * @return array{value: T, expiresAt: int|null}|null
     */
    private function loadEntry(string $key): ?array
    {
        if (isset($this->memory[$key])) {
            $entry = $this->memory[$key];

            if ($this->isExpired($entry)) {
                unset($this->memory[$key]);
                $this->deleteEntryFile($key);

                return null;
            }

            return $entry;
        }

        $entry = $this->readEntry($key);
        if ($entry === null) {
            return null;
        }

        if ($this->isExpired($entry)) {
            $this->deleteEntryFile($key);

            return null;
        }

        $this->memory[$key] = $entry;

        return $entry;
    }

    /**
     * @param array{value: T, expiresAt: int|null} $entry
     */
    private function isExpired(array $entry): bool
    {
        return $entry['expiresAt'] !== null && $entry['expiresAt'] <= time();
    }

    private function entryPath(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . sha1($key) . '.php';
    }

    /**
     * @return array{value: T, expiresAt: int|null}|null
     */
    private function readEntry(string $key): ?array
    {
        $file = $this->entryPath($key);

        if (!is_file($file)) {
            return null;
        }

        /** @var array{value: T, expiresAt: int|null} $entry */
        $entry = require $file;

        return $entry;
    }

    private function deleteEntryFile(string $key): void
    {
        $file = $this->entryPath($key);
        if (is_file($file)) {
            // Suppressed: a TTL-expired entry in a read-only directory is
            // evicted from memory on the read path; disk removal is best-effort.
            @unlink($file);
            self::invalidateOpcacheFor($file);
        }
    }

    /**
     * @param array{value: T, expiresAt: int|null} $entry
     */
    private function writeEntryToDisk(string $key, array $entry): void
    {
        self::writeAtomically($this->entryPath($key), $entry);
    }

    private static function invalidateOpcacheFor(string $file): void
    {
        if (function_exists('opcache_invalidate')) {
            /** @psalm-suppress UndefinedFunction */
            opcache_invalidate($file, true);
        }
    }

    /**
     * Defensive normalization of the cache directory input:
     *
     *   - trim surrounding whitespace
     *   - if a Windows-style absolute path (e.g. `C:\...`) is embedded mid-string,
     *     keep only the substring from the last such occurrence. This protects against
     *     callers that accidentally concatenate `getcwd()` with an already-absolute
     *     path such as `sys_get_temp_dir()` on Windows
     *   - fold both `/` and `\` to `DIRECTORY_SEPARATOR`, preserving a leading
     *     `\\` UNC prefix on Windows
     *   - collapse runs of the separator and trim trailing separators
     */
    private function normalizeDirectory(string $dir): string
    {
        $dir = trim($dir);

        $count = preg_match_all('#[A-Za-z]:[\\\\/]#', $dir, $matches, PREG_OFFSET_CAPTURE);
        if ($count !== false && $count > 1) {
            $positions = $matches[0];
            $lastOffset = $positions[$count - 1][1];
            if ($lastOffset > 0) {
                $dir = substr($dir, $lastOffset);
            }
        }

        $dir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);

        $uncPrefix = '';
        if (DIRECTORY_SEPARATOR === '\\' && str_starts_with($dir, '\\\\')) {
            $uncPrefix = '\\\\';
            $dir = substr($dir, 2);
        }

        $collapsed = preg_replace(
            '#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '{2,}#',
            DIRECTORY_SEPARATOR,
            $dir,
        );
        $dir = $collapsed ?? $dir;

        return $uncPrefix . rtrim($dir, '/\\');
    }
}
