<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ClassLoading;

use Throwable;

use function sprintf;

final class ClassLoadFailures
{
    /** @var array<string, string> Survives framework resets because PHP cannot unload these failed declarations before the process ends. */
    private static array $definedFailures = [];

    public static function reasonFor(string $className): ?string
    {
        return self::$definedFailures[strtolower($className)] ?? null;
    }

    public static function capture(string $className, Throwable $error): void
    {
        if (class_exists($className, false) || interface_exists($className, false) || trait_exists($className, false)) {
            self::$definedFailures[strtolower($className)] = self::describe($error);
        }

    }

    public static function describe(Throwable $error): string
    {
        return sprintf('%s in %s:%d', $error->getMessage(), $error->getFile(), $error->getLine());
    }
}
