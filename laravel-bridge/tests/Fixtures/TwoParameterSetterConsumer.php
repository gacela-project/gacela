<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\LaravelBridge\Attribute\Inject;

/**
 * An explicit implementation over two parameters: there is no saying which
 * one it was meant for, so the listener must refuse rather than guess.
 */
final class TwoParameterSetterConsumer
{
    /** @var list<ServiceContract> */
    public array $received = [];

    #[Inject(CountingService::class)]
    public function setServices(ServiceContract $first, CountingService $second): void
    {
        $this->received = [$first, $second];
    }
}
