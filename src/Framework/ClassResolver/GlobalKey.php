<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function ltrim;
use function preg_match;
use function sprintf;

final class GlobalKey
{
    /**
     * Unify the keys for the class resolver.
     */
    public static function fromClassName(string $fullClassName): string
    {
        preg_match('~(?<pre_namespace>.*)\\\(?<resolvable_type>.*)~', $fullClassName, $matches);

        $resolvableType = ResolvableType::fromClassName($matches['resolvable_type'] ?? '');

        if (empty($resolvableType->moduleName())) {
            return sprintf('\\%s', ltrim($fullClassName, '\\'));
        }

        return sprintf(
            '\\%s\\%s',
            ltrim($matches['pre_namespace'], '\\'),
            $resolvableType->resolvableType()
        );
    }
}
