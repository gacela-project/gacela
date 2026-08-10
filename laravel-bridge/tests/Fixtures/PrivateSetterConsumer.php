<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

final class PrivateSetterConsumer
{
    private ?CountingService $service = null;

    public function service(): ?CountingService
    {
        return $this->service;
    }

    #[Inject]
    private function setService(CountingService $service): void
    {
        $this->service = $service;
    }
}
