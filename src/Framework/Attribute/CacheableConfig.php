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

    /** @var array<string,int> */
    private static array $ttlOverrides = [];

    public static function setStorage(CacheStorageInterface $storage): void
    {
        self::$storage = $storage;
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
        self::$ttlOverrides = [];
    }
}
