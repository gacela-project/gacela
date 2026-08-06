<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies\Fixtures;

use Gacela\Framework\Attribute\Inject;

/**
 * The same shape as {@see InjectService}, spelled with the framework attribute
 * instead of the container one. Both are supported imports, so `debug:*` must
 * report them identically.
 */
final class FrameworkInjectService
{
    public function __construct(
        #[Inject]
        public readonly BoundContract $plain,
        #[Inject(BoundImplementation::class)]
        public readonly BoundContract $withOverride,
    ) {
    }
}
