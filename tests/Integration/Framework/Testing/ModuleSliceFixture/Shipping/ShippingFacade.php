<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shipping;

use Gacela\Framework\AbstractFacade;

/**
 * Open for the same reason PricingFacade is: a consumer that type-hints a
 * `final` Facade cannot be handed a double of it by anyone.
 *
 * @extends AbstractFacade<ShippingFactory>
 */
class ShippingFacade extends AbstractFacade
{
    public function costOf(string $article): int
    {
        return $this->getFactory()->costOf($article);
    }
}
