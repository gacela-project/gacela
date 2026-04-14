<?php

declare(strict_types=1);

namespace Gacela\Framework\ServiceResolver;

use ReflectionClass;

/**
 * @internal
 */
final class ReflectionClassPool
{
    /** @var array<class-string, ReflectionClass<object>> */
    private static array $cache = [];

    /**
     * @param class-string $className
     *
     * @return ReflectionClass<object>
     */
    public static function get(string $className): ReflectionClass
    {
        return self::$cache[$className] ??= new ReflectionClass($className);
    }

    public static function reset(): void
    {
        self::$cache = [];
    }
}
