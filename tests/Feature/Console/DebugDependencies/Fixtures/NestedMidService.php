<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies\Fixtures;

final class NestedMidService
{
    public function __construct(
        public readonly BoundContract $bound,
        public readonly AutowirableCollaborator $collaborator,
        public readonly UnboundContract $unbound,
    ) {
    }
}
