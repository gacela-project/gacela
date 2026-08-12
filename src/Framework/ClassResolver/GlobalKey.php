<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function sprintf;

final class GlobalKey
{
    /** @var array<string,string> */
    private static array $cache = [];

    private static int $generation = 0;

    /**
     * Unify the keys for the class resolver.
     */
    public static function fromClassName(string $fullClassName): string
    {
        // A normalized key answers which suffix names a kind. When the declared
        // kinds move, every answer here was about the old set.
        if (self::$generation !== ResolvableTypes::generation()) {
            self::$generation = ResolvableTypes::generation();
            self::$cache = [];
        }

        if (isset(self::$cache[$fullClassName])) {
            return self::$cache[$fullClassName];
        }

        preg_match('~(?<pre_namespace>.*)\\\(?<resolvable_type>.*)~', $fullClassName, $matches);

        $resolvableType = ResolvableType::fromClassName($matches['resolvable_type'] ?? '');

        if ($resolvableType->moduleName() === '') {
            return self::$cache[$fullClassName] = sprintf('\\%s', ltrim($fullClassName, '\\'));
        }

        self::$cache[$fullClassName] = sprintf(
            '\\%s\\%s',
            ltrim($matches['pre_namespace'] ?? '', '\\'),
            $resolvableType->resolvableType(),
        );

        return self::$cache[$fullClassName];
    }
}
