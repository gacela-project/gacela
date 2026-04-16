<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Container\Fixtures;

use Gacela\Container\Attribute\Inject;

final class InjectConsumer
{
    public function __construct(
        #[Inject(ConcreteService::class)] public readonly ServiceInterface $service,
    ) {
    }
}
