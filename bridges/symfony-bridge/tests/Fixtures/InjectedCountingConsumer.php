<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

final class InjectedCountingConsumer
{
    public function __construct(
        #[Inject] public readonly CountingService $counting,
    ) {
    }
}
