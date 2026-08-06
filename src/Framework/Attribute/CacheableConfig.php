<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

/**
 * Process-wide configuration for the #[Cacheable] feature.
 *
 * - Storage backend (defaults to in-memory per-process cache).
 * - Per-method TTL overrides, keyed by "Fully\Qualified\Class::method".
 *
 * Configure once at bootstrap; values are shared by every facade using CacheableTrait.
 */
final class CacheableConfig
{
    private static ?CacheStorageInterface $storage = null;

    /**
     * Whether the storage above came from the application rather than from
     * getStorage()'s lazy default. `Gacela::resetCache()` is an in-memory
     * reset: it clears the default, and must leave a registered backend alone.
     * That one is typically APCu or Redis, shared with the rest of the
     * application, where `clear()` removes everything and not just Gacela's
     * entries.
     */
    private static bool $storageIsUserSupplied = false;

    /** @var array<string,int> */
    private static array $ttlOverrides = [];

    public static function setStorage(CacheStorageInterface $storage): void
    {
        self::$storage = $storage;
        self::$storageIsUserSupplied = true;
    }

    /**
     * Clear the method cache only when it is the framework's own in-memory
     * default. Call `CacheableTrait::clearMethodCache()` to clear regardless of
     * who registered the backend.
     */
    public static function clearDefaultStorage(): void
    {
        if (self::$storageIsUserSupplied) {
            return;
        }

        self::$storage?->clear();
    }

    public static function getStorage(): CacheStorageInterface
    {
        return self::$storage ??= new InMemoryCacheStorage();
    }

    /**
     * @param array<string,int> $overrides map of "Class::method" => ttl in seconds
     */
    public static function setTtlOverrides(array $overrides): void
    {
        self::$ttlOverrides = $overrides;
    }

    public static function resolveTtl(string $classMethod, int $default): int
    {
        return self::$ttlOverrides[$classMethod] ?? $default;
    }

    public static function reset(): void
    {
        self::$storage = null;
        self::$storageIsUserSupplied = false;
        self::$ttlOverrides = [];
    }
}
