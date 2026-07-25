<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ValidateConfig\Fixtures;

/**
 * The other half of the cycle declared by {@see CyclicA}.
 */
final class CyclicB
{
    public function __construct(
        public readonly CyclicA $a,
    ) {
    }
}
