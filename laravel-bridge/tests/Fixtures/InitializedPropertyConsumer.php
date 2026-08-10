<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

/**
 * The property already holds a value -- null, on purpose. Injection fills what
 * construction left unset; it does not overrule what construction decided.
 */
final class InitializedPropertyConsumer
{
    #[Inject]
    private ?CountingService $service = null;

    public function service(): ?CountingService
    {
        return $this->service;
    }
}
