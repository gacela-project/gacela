<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\LaravelBridge\Attribute\Inject;

final class BareConstructorConsumer
{
    public function __construct(
        #[Inject]
        public CountingService $service,
    ) {
    }
}
