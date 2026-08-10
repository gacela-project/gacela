<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

/**
 * Annotated with the *Gacela* attribute, not the bridge's: the listener must
 * honor either namespace, the way Gacela's own resolver does.
 */
final class PropertyConsumer
{
    #[Inject]
    private CountingService $service;

    public function service(): CountingService
    {
        return $this->service;
    }
}
