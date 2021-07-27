<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

final class GlobalKey
{
    public static function fromClassName(string $className): string
    {
        preg_match('~(?<pre_namespace>.*)\\\((?:^|[A-Z])[a-z]+)(?<resolvable_type>.*)~', $className, $matches);
        $resolvableType = $matches['resolvable_type'] ?? '';

        return (empty($resolvableType) || $resolvableType === 'Provider')
            ? sprintf('\\%s', ltrim($className, '\\'))
            : sprintf('\\%s\\%s', ltrim($matches['pre_namespace'], '\\'), $resolvableType);
    }
}
