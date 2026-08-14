<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies\Fixtures;

use Gacela\Container\Attribute\Inject;

/**
 * An `#[Inject]` naming a class that is not there -- a rename or a deleted
 * class. The container reads the name only when it builds, so nothing else
 * reports it.
 */
final class InjectMissingImplementationService
{
    public function __construct(
        #[Inject('GacelaTest\Fixtures\NoSuchImplementation')] public readonly UnboundContract $gone,
    ) {
    }
}
