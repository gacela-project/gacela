<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\LaravelBridge\Attribute\Inject;

final class ConstructorConsumer
{
    public function __construct(
        #[Inject(CountingService::class)]
        public CountingService $service,
    ) {
    }
}
