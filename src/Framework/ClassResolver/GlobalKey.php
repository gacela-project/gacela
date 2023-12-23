<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

final class GlobalKey
{
    /**
     * Unify the keys for the class resolver.
     */
    public static function fromClassName(string $fullClassName): string
    {
        preg_match('~(?<pre_namespace>.*)\\\(?<resolvable_type>.*)~', $fullClassName, $matches);

        $resolvableType = ResolvableType::fromClassName($matches['resolvable_type'] ?? '');

        if ($resolvableType->moduleName() === '' || $resolvableType->moduleName() === '0') {
            return sprintf('\\%s', ltrim($fullClassName, '\\'));
        }

        return sprintf(
            '\\%s\\%s',
            ltrim($matches['pre_namespace'], '\\'),
            $resolvableType->resolvableType(),
        );
    }
}
