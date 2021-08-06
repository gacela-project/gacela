<?php

declare(strict_types=1);

namespace Gacela\Framework\Util;

/**
 * This class is a helper to store global services if needed.
 *
 * For example, to be able to access the kernel data from your framework
 * in order to get the real (already instantiated) services and inject them
 * as dependencies in your gacela.php config file.
 *
 * This util class is not used for the class resolver.
 */
final class GlobalServices
{
    /** @var array<string,object> Global instances */
    private static array $globalInstances = [];

    public static function add(string $key, object $resolvedClass): void
    {
        self::$globalInstances[$key] = $resolvedClass;
    }

    public static function get(string $key): ?object
    {
        return self::$globalInstances[$key] ?? null;
    }
}
