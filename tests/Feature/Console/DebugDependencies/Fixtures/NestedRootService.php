<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies\Fixtures;

/**
 * Its own constructor names a single collaborator, so the one-level view says
 * nothing about the three dependencies one step further down.
 */
final class NestedRootService
{
    public function __construct(
        public readonly NestedMidService $mid,
    ) {
    }
}
