<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing;

use Gacela\Framework\AbstractConfig;

/**
 * Not final, so a test can double it with an anonymous subclass -- the shape
 * {@see \GacelaTest\Integration\Framework\Testing\ModuleDoublesTest} pins for
 * `swapModuleConfig()` and that a slice has to route to unchanged.
 */
class PricingConfig extends AbstractConfig
{
    public function currency(): string
    {
        return 'EUR';
    }
}
