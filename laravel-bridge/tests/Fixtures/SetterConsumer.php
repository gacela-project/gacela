<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\LaravelBridge\Attribute\Inject;

final class SetterConsumer
{
    private ?CountingService $service = null;

    #[Inject]
    public function setService(CountingService $service): void
    {
        $this->service = $service;
    }

    public function service(): ?CountingService
    {
        return $this->service;
    }
}
