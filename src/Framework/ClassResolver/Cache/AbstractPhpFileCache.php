<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use Gacela\Framework\Cache\FileCache;

use function array_keys;
use function file_exists;
use function sha1;
use function str_ends_with;
use function substr;

use const DIRECTORY_SEPARATOR;

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
        private readonly string $appRootDir = '',
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
        return self::$cache[static::class] ?? [];
    }

    /**
     * Clears the in-memory entries of *every* file-backed cache, so
     * `Gacela::resetCache()` does not leave a subclass serving what it read
     * from disk before the reset.
     *
     * The per-subclass {@see clearStaticCache()} below has existed since the
     * batching work, but nothing outside the test suite ever called it: the
     * central reset cannot name concrete subclasses, and a caller that could
     * would have to know all of them. This one is keyless, so it does not need
     * to.
     *
     * Nothing is kept back, including the filename registry: `put()` re-registers
     * the filename of whatever it writes, and `all()` reads through a missing
     * slot as empty.
     *
     * @internal
     */
    public static function resetCache(): void
    {
        self::$cache = [];
        self::$filenames = [];
        self::$dirty = [];
        self::$batching = false;
    }

    /**
     * Clears one subclass's in-memory cache entries and any shared batch state,
     * leaving the other subclasses alone. Intended for tests that need to
     * isolate a single cache within a process; {@see resetCache()} is what the
     * framework calls.
     *
     * The filename registry is left alone here, and that is now a detail rather
     * than a constraint: `put()` re-registers the filename on every write, so
     * dropping it would be safe too.
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

        foreach (array_keys(self::$dirty) as $class) {
            $filename = self::$filenames[$class] ?? null;
            if ($filename === null) {
                continue;
            }

            FileCache::writeAtomically($filename, self::$cache[$class] ?? []);
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
        return static::all();
    }

    public function put(string $cacheKey, string $className): void
    {
        if (isset(self::$cache[static::class][$cacheKey])
            && self::$cache[static::class][$cacheKey] === $className
        ) {
            return;
        }

        self::$cache[static::class][$cacheKey] = $className;

        // Re-registered on every write, not just in the constructor: the
        // registry is what commitBatch() -- which is static, and so has no
        // instance to ask -- resolves the file from, and resetCache() empties
        // it. Without this, a put() after a reset would mark the cache dirty
        // and then be silently dropped by the next commit.
        self::$filenames[static::class] = $this->computeAbsoluteFilename();

        if (self::$batching) {
            self::$dirty[static::class] = true;
            return;
        }

        FileCache::writeAtomically(self::$filenames[static::class], self::$cache[static::class]);
    }

    /**
     * Where a cache file for this app lives.
     *
     * The cache dir can be shared between applications -- it defaults to the
     * system temp dir -- so the filename embeds a hash of the app root. Without
     * it two applications on one machine read and write the same file, and one
     * silently serves the other's resolved class names. That is the defect
     * `MergedConfigCache` was given this treatment for; these two caches were
     * left behind, and a fresh install picking up another project's fixtures is
     * what surfaced it.
     *
     * Public and static so the console agrees with the cache on the path:
     * `cache:clear` and the staleness check have to look where `put()` wrote.
     */
    public static function absoluteFilename(string $cacheDir, string $baseFilename, string $appRootDir): string
    {
        if ($appRootDir === '') {
            return $cacheDir . DIRECTORY_SEPARATOR . $baseFilename;
        }

        $base = str_ends_with($baseFilename, '.php')
            ? substr($baseFilename, 0, -4)
            : $baseFilename;

        return $cacheDir . DIRECTORY_SEPARATOR . $base . '-' . substr(sha1($appRootDir), 0, 12) . '.php';
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
        return self::absoluteFilename($this->cacheDir, $this->getCacheFilename(), $this->appRootDir);
    }
}
