<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies\Fixtures;

final class MixedDependenciesService
{
    public function __construct(
        public readonly BoundContract $bound,
        public readonly AutowirableCollaborator $collaborator,
        public readonly UnboundContract $unbound,
        public readonly string $mandatoryScalar,
        public readonly string $optionalScalar = 'default',
        public readonly ?AutowirableCollaborator $nullableCollaborator = null,
    ) {
    }
}
