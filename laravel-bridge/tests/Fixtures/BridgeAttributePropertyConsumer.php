<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\LaravelBridge\Attribute\Inject;

/**
 * The other half of {@see PropertyConsumer}: the *bridge's* attribute on a
 * property.
 *
 * The README shows the bridge attribute first, for the constructor case, so it
 * is the import a reader already has when they reach the property example --
 * and the attribute declares `TARGET_PROPERTY`, so nothing stops them. It works
 * because the listener matches `IS_INSTANCEOF` and this one extends the Gacela
 * attribute; a matcher tightened to the exact class would break it silently,
 * which is the failure an attribute always has.
 */
final class BridgeAttributePropertyConsumer
{
    #[Inject]
    private CountingService $service;

    public function service(): CountingService
    {
        return $this->service;
    }
}
