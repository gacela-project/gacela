<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies\Fixtures;

final class TwoUnresolvableService
{
    public function __construct(
        public readonly UnboundContract $first,
        public readonly SecondUnboundContract $second,
    ) {
    }
}
