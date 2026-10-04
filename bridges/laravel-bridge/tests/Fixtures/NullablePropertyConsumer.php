<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

/**
 * Null by default and nothing else sets it: injected, as Gacela's own
 * container does.
 */
final class NullablePropertyConsumer
{
    #[Inject]
    private ?CountingService $service = null;

    public function service(): ?CountingService
    {
        return $this->service;
    }
}
