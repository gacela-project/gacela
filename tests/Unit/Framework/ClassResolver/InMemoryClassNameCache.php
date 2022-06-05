<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ClassNameCacheInterface;

final class InMemoryClassNameCache implements ClassNameCacheInterface
{
    /** @var array<string,string> */
    private static array $cachedClassNames = [];

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
