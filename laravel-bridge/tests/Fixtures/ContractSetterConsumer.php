<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\LaravelBridge\Attribute\Inject;

/**
 * The parameter names the contract; the attribute names the implementation.
 * Only the implementation is bound, so resolving the parameter type instead
 * would fail loudly -- which is what makes the override observable.
 */
final class ContractSetterConsumer
{
    private ?ServiceContract $service = null;

    public function service(): ?ServiceContract
    {
        return $this->service;
    }

    #[Inject(CountingService::class)]
    public function setService(ServiceContract $service): void
    {
        $this->service = $service;
    }
}
