<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ValidateConfig\Fixtures;

/**
 * Half of a deliberate constructor-injection cycle: A needs B, B needs A.
 * Resolving it makes the container throw a CircularDependencyException.
 */
final class CyclicA implements CyclicContract
{
    public function __construct(
        public readonly CyclicB $b,
    ) {
    }
}
