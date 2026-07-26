<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ValidateConfig\Fixtures;

use Gacela\Container\Exception\CircularDependencyException;

/**
 * Reports a cycle whose chain starts immediately after the colon, so nothing
 * of the chain may be dropped while stripping the headline's prefix.
 */
final class ThrowsColonPackedCycle implements SomeContract
{
    public const CHAIN = 'App\\Alpha -> App\\Beta -> App\\Alpha';

    public const HEADLINE = 'Circular dependency detected:' . self::CHAIN;

    public function __construct()
    {
        throw new CircularDependencyException(self::HEADLINE);
    }
}
