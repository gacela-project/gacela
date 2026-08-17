<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

/**
 * Two `#[Inject]` properties, one of them private on the parent: the child's
 * reflection cannot see it, and the listener must walk up for it anyway.
 */
final class InheritedPropertiesConsumer extends BaseWithPrivateProperty
{
    #[Inject]
    private CountingService $ownService;

    public function ownService(): CountingService
    {
        return $this->ownService;
    }
}
