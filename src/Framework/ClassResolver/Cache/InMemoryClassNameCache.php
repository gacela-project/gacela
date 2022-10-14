<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

final class InMemoryClassNameCache implements ClassNameCacheInterface
{
    /** @var array<string, array<string,string>> */
    private static array $cache = [];

    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * @internal
     */
    public static function getAllFromKey(string $key): array
    {
        return self::$cache[$key] ?? [];
    }

    /**
     * @internal
     */
    public static function resetCache(): void
    {
        self::$cache = [];
    }

    /**
     * @return array<string, string>
     */
    public function getAll(): array
    {
        return self::$cache[$this->key] ?? [];
    }

    public function has(string $cacheKey): bool
    {
        return isset(self::$cache[$this->key][$cacheKey]);
    }

    public function get(string $cacheKey): string
    {
        return self::$cache[$this->key][$cacheKey];
    }

    public function put(string $cacheKey, string $className): void
    {
        self::$cache[$this->key][$cacheKey] = $className;
    }
}
