<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use RuntimeException;

use function bin2hex;
use function random_bytes;
use function sprintf;

use const LOCK_EX;

abstract class AbstractPhpFileCache implements CacheInterface
{
    /** @var array<class-string,array<string,string>> */
    private static array $cache = [];

    /** @var array<class-string,string> */
    private static array $filenames = [];

    /** @var array<class-string,true> */
    private static array $dirty = [];

    private static bool $batching = false;

    public function __construct(
        private readonly string $cacheDir,
    ) {
        self::$cache[static::class] = $this->getExistingCache();
        self::$filenames[static::class] = $this->computeAbsoluteFilename();
    }

    /**
     * @internal
     *
     * @return array<string,string>
     */
    public static function all(): array
    {
        return self::$cache[static::class];
    }

    /**
     * Clears this class's in-memory cache entries and any shared batch state.
     * Intended for tests to ensure isolation across runs in the same PHP process.
     *
     * The filename registry is intentionally preserved: the absolute cache file
     * path is a deterministic function of the cache directory and subclass, so
     * it stays valid across clear/reconstruct cycles. Dropping it here would
     * strand any already-constructed instance held by an outer cache holder
     * (e.g. ClassResolverCache::$cache) without a way to recover the filename
     * on a subsequent put().
     *
     * @internal
     */
    public static function clearStaticCache(): void
    {
        self::$cache[static::class] = [];
        unset(self::$dirty[static::class]);
        self::$batching = false;
    }

    /**
     * Start accumulating put() calls in memory without touching disk. Intended for
     * long-running warming operations where hundreds of entries would otherwise
     * trigger hundreds of full-file rewrites.
     *
     * The batch state is shared across every file-backed cache, so callers don't
     * need to know which concrete cache classes exist.
     */
    public static function beginBatch(): void
    {
        self::$batching = true;
    }

    /**
     * Flush any accumulated puts to disk in a single atomic write per concrete
     * cache class that was modified during the batch. A no-op if no batch was
     * in progress.
     */
    public static function commitBatch(): void
    {
        if (!self::$batching) {
            return;
        }

        self::$batching = false;

        foreach (self::$dirty as $class => $_) {
            $filename = self::$filenames[$class] ?? null;
            if ($filename === null) {
                continue;
            }

            self::writeAtomic($filename, self::$cache[$class] ?? []);
        }

        self::$dirty = [];
    }

    public static function isBatching(): bool
    {
        return self::$batching;
    }

    public function has(string $cacheKey): bool
    {
        return isset(self::$cache[static::class][$cacheKey]);
    }

    public function get(string $cacheKey): string
    {
        return self::$cache[static::class][$cacheKey];
    }

    /**
     * @return array<string,string>
     */
    public function getAll(): array
    {
        return self::$cache[static::class];
    }

    public function put(string $cacheKey, string $className): void
    {
        if (isset(self::$cache[static::class][$cacheKey])
            && self::$cache[static::class][$cacheKey] === $className
        ) {
            return;
        }

        self::$cache[static::class][$cacheKey] = $className;

        if (self::$batching) {
            self::$dirty[static::class] = true;
            return;
        }

        self::writeAtomic(self::$filenames[static::class], self::$cache[static::class]);
    }

    abstract protected function getCacheFilename(): string;

    /**
     * @return array<string,string>
     */
    private function getExistingCache(): array
    {
        $filename = $this->computeAbsoluteFilename();

        if (file_exists($filename)) {
            /** @var array<string,string> $content */
            $content = require $filename;

            return $content;
        }

        return [];
    }

    private function computeAbsoluteFilename(): string
    {
        if (!is_dir($this->cacheDir)
            && !mkdir($concurrentDirectory = $this->cacheDir, 0777, true)
            && !is_dir($concurrentDirectory)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        return $this->cacheDir . DIRECTORY_SEPARATOR . $this->getCacheFilename();
    }

    /**
     * Write atomically: stage to a sibling .tmp file then rename. rename() is
     * atomic on POSIX filesystems, so readers never see a half-written cache.
     *
     * @param array<string,string> $entries
     */
    private static function writeAtomic(string $filename, array $entries): void
    {
        $fileContent = sprintf('<?php return %s;', var_export($entries, true));
        $tmp = $filename . '.' . bin2hex(random_bytes(4)) . '.tmp';

        file_put_contents($tmp, $fileContent, LOCK_EX);
        rename($tmp, $filename);
    }
}
