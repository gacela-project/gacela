<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use Throwable;

use function class_exists;
use function interface_exists;
use function is_a;

final class LoadedClass
{
    public static function isA(string $class, string $parent): bool
    {
        try {
            /** @psalm-suppress ArgumentTypeCoercion Configured names may not resolve. */
            return is_a($class, $parent, true);
        } catch (Throwable) {
            return false;
        }
    }

    public static function exists(string $class): bool
    {
        try {
            return class_exists($class) || interface_exists($class);
        } catch (Throwable) {
            return false;
        }
    }
}
