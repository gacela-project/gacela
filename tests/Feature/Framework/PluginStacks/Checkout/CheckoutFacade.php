<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginStacks\Checkout;

use Gacela\Framework\AbstractFacade;

/**
 * @method CheckoutFactory getFactory()
 */
final class CheckoutFacade extends AbstractFacade
{
    public function priceOf(int $cents): int
    {
        foreach ($this->getFactory()->createDiscounts() as $discount) {
            $cents = $discount->apply($cents);
        }

        return $cents;
    }
}
