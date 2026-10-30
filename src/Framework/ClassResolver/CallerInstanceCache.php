<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use stdClass;

/**
 * @internal
 */
final class CallerInstanceCache
{
    /** @var array<string, array<string, null|object>> */
    public static array $instances = [];

    private static ?stdClass $token = null;

    public static function token(): stdClass
    {
        return self::$token ??= new stdClass();
    }

    public static function isCurrent(stdClass $token): bool
    {
        return self::$token === $token;
    }

    public static function reset(): void
    {
        self::$token = null;
        self::$instances = [];
    }
}
