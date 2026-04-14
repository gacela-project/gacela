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

    /** @var array<class-string,bool> */
    private static array $batchMode = [];

    /** @var array<class-string,string> */
    private static array $filenames = [];

    public function __construct(
        private readonly string $cacheDir,
    ) {
        self::$cache[static::class] = $this->getExistingCache();
        self::$filenames[static::class] = $this->getAbsoluteCacheFilename();
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
     * Clears the static in-memory cache.
     * Useful for testing to ensure test isolation when tests run in the same PHP process.
     *
     * @internal
     */
    public static function clearStaticCache(): void
    {
        self::$cache[static::class] = [];
        unset(self::$batchMode[static::class]);
    }

    /**
     * Start accumulating put() calls in memory without touching disk. Intended for
     * long-running warming operations where hundreds of entries would otherwise
     * trigger hundreds of full-file rewrites.
     */
    public static function beginBatch(): void
    {
        self::$batchMode[static::class] = true;
    }

    /**
     * Flush accumulated put() calls to disk in a single atomic write. A no-op if
     * beginBatch() was never called or if no instance has been constructed yet
     * (which means the filename is unknown).
     */
    public static function commitBatch(): void
    {
        $wasBatching = self::$batchMode[static::class] ?? false;
        unset(self::$batchMode[static::class]);

        if (!$wasBatching) {
            return;
        }

        $filename = self::$filenames[static::class] ?? null;
        if ($filename === null) {
            return;
        }

        self::writeAtomic($filename, self::$cache[static::class] ?? []);
    }

    public static function isBatching(): bool
    {
        return self::$batchMode[static::class] ?? false;
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

        if (self::$batchMode[static::class] ?? false) {
            return;
        }

        self::writeAtomic($this->getAbsoluteCacheFilename(), self::$cache[static::class]);
    }

    abstract protected function getCacheFilename(): string;

    /**
     * @return array<string,string>
     */
    private function getExistingCache(): array
    {
        $filename = $this->getAbsoluteCacheFilename();

        if (file_exists($filename)) {
            /** @var array<string,string> $content */
            $content = require $filename;

            return $content;
        }

        return [];
    }

    private function getAbsoluteCacheFilename(): string
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
