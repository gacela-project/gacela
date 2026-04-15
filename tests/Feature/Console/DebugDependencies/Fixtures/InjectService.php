<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies\Fixtures;

use Gacela\Container\Attribute\Inject;

final class InjectService
{
    public function __construct(
        #[Inject] public readonly BoundContract $plain,
        #[Inject(BoundImplementation::class)] public readonly BoundContract $withOverride,
    ) {
    }
}
