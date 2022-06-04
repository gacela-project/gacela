<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

final class ClassNameCache implements ClassNameCacheInterface
{
    /** @var array<string,string> */
    private static array $cachedClassNames = [];

    /**
     * @param array<string,string> $cachedClassNames
     */
    public function __construct(array $cachedClassNames = [])
    {
        self::$cachedClassNames = $cachedClassNames;
    }

    /**
     * @internal
     */
    public static function resetCachedClassNames(): void
    {
        self::$cachedClassNames = [];
    }

    public function has(string $cacheKey): bool
    {
        return isset(self::$cachedClassNames[$cacheKey]);
    }

    public function get(string $cacheKey): string
    {
        return self::$cachedClassNames[$cacheKey];
    }

    public function put(string $cacheKey, string $className): void
    {
        self::$cachedClassNames[$cacheKey] = $className;
    }
}
