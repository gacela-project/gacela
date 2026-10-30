<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function sprintf;

final class GlobalKey
{
    /** @var array<string,string> */
    private static array $cache = [];

    /**
     * A normalized key answers which suffix names a kind, so every entry is
     * about the declarations in force when it was computed. Cleared by
     * whoever changes them -- checking a stamp here instead cost more than the
     * lookup it guarded, on the hottest path in resolution.
     *
     * @internal
     */
    public static function resetCache(): void
    {
        self::$cache = [];
    }

    /**
     * Unify the keys for the class resolver.
     */
    public static function fromClassName(string $fullClassName): string
    {
        if (isset(self::$cache[$fullClassName])) {
            return self::$cache[$fullClassName];
        }

        $normalized = ltrim($fullClassName, '\\');
        if (!str_contains($normalized, '\\')) {
            return self::$cache[$fullClassName] = '\\' . $normalized;
        }

        preg_match('~(?<pre_namespace>.*)\\\(?<resolvable_type>.*)~', $fullClassName, $matches);

        $resolvableType = ResolvableType::fromClassName($matches['resolvable_type'] ?? '');

        if ($resolvableType->moduleName() === '' || self::isCustomDeclaredClass($matches['pre_namespace'] ?? '', $resolvableType)) {
            return self::$cache[$fullClassName] = sprintf('\\%s', ltrim($fullClassName, '\\'));
        }

        self::$cache[$fullClassName] = sprintf(
            '\\%s\\%s',
            ltrim($matches['pre_namespace'] ?? '', '\\'),
            $resolvableType->resolvableType(),
        );

        return self::$cache[$fullClassName];
    }

    private static function isCustomDeclaredClass(string $namespace, ResolvableType $type): bool
    {
        if (isset(ResolvableTypes::BUILT_IN[$type->resolvableType()])) {
            return false;
        }

        $namespaceParts = explode('\\', $namespace);

        return $type->moduleName() !== array_pop($namespaceParts);
    }

}
