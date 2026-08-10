<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

final class ReadonlyPropertyConsumer
{
    #[Inject]
    private readonly CountingService $service;

    public function service(): CountingService
    {
        return $this->service;
    }
}
