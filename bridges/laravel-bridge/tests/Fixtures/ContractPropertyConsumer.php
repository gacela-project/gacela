<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\LaravelBridge\Attribute\Inject;

/**
 * The property names the contract; the attribute names the implementation.
 * Only the implementation is bound, so resolving the property type instead
 * would fail loudly -- which is what makes the override observable.
 */
final class ContractPropertyConsumer
{
    #[Inject(CountingService::class)]
    private ServiceContract $service;

    public function service(): ServiceContract
    {
        return $this->service;
    }
}
