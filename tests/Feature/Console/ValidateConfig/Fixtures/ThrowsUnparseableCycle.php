<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ValidateConfig\Fixtures;

use Gacela\Container\Exception\CircularDependencyException;

/**
 * Reports a cycle whose headline carries no "detected: chain" separator, which
 * is the case the command has to print verbatim instead of trimming.
 */
final class ThrowsUnparseableCycle implements SomeContract
{
    public const HEADLINE = 'A cycle without a separator';

    public function __construct()
    {
        throw new CircularDependencyException(self::HEADLINE);
    }
}
