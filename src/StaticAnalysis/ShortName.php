<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use function strrpos;
use function substr;

/**
 * The last segment of a fully qualified name.
 *
 * The pillar rules are all about the suffix of a class name -- `*Facade`,
 * `*Factory` -- and matching that against the qualified name would let a
 * namespace segment stand in for the class, so the trimming happens once here.
 */
final class ShortName
{
    public static function of(string $className): string
    {
        $pos = strrpos($className, '\\');

        return $pos === false ? $className : substr($className, $pos + 1);
    }
}
