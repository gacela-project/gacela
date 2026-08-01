<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies\Fixtures;

final class CyclicRightService
{
    public function __construct(
        public readonly CyclicLeftService $left,
    ) {
    }
}
