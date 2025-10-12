<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function sprintf;

final class GlobalKey
{
    /** @var array<string,string> */
    private static array $cache = [];

    /**
     * Unify the keys for the class resolver.
     */
    public static function fromClassName(string $fullClassName): string
    {
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
