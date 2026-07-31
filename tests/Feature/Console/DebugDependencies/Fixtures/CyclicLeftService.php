<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies\Fixtures;

/**
 * Half of a constructor cycle. The container reports where the graph closes
 * rather than throwing, because a broken graph is exactly what you open a
 * dependency inspector to look at.
 */
final class CyclicLeftService
{
    public function __construct(
        public readonly CyclicRightService $right,
    ) {
    }
}
