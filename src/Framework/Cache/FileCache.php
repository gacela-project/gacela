<?php

declare(strict_types=1);

namespace Gacela\Framework\Cache;

use RuntimeException;

use function bin2hex;
use function count;
use function fclose;
use function file_put_contents;
use function filemtime;
use function filesize;
use function flock;
use function fopen;
use function function_exists;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rename;
use function sha1;
use function sprintf;
use function time;
use function unlink;
use function var_export;

use const DIRECTORY_SEPARATOR;
use const LOCK_EX;
use const LOCK_UN;

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
 *
 * @template T
 */
final class FileCache
{
    private const INDEX_FILENAME = '.gacela-filecache.lock';

    /** @var array<string, array{value: T, expiresAt: int|null}> */
    private array $memory = [];

    private bool $batching = false;

    /** @var array<string, array{value: T, expiresAt: int|null}> */
    private array $batchPending = [];

    public function __construct(
        public readonly string $directory,
        public readonly int $defaultTtl = 0,
    ) {
        $this->ensureDirectory();
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

        $file = $this->entryPath($key);
        if (is_file($file)) {
            unlink($file);
            self::invalidateOpcacheFor($file);
        }
    }

    public function clear(): void
    {
        $this->memory = [];
        $this->batchPending = [];

        $files = glob($this->directory . '/*.php') ?: [];
        foreach ($files as $file) {
            unlink($file);
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
        $handle = fopen($indexPath, 'c');

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
     * Exposed so other file-backed caches in the framework (e.g.
     * {@see \Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache}) can
     * share exactly one write path.
     *
     * @param mixed $value any `var_export`-safe payload
     */
    public static function writeAtomically(string $file, mixed $value): void
    {
        $content = sprintf('<?php return %s;', var_export($value, true));
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        file_put_contents($tmp, $content, LOCK_EX);
        rename($tmp, $file);

        self::invalidateOpcacheFor($file);
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
            unlink($file);
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

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!mkdir($concurrentDirectory = $this->directory, recursive: true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
    }
}
