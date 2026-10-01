<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginAttribute\Checkout;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<CheckoutFactory>
 */
final class CheckoutFacade extends AbstractFacade
{
    /**
     * @return list<string>
     */
    public function discountNames(): array
    {
        $names = [];
        foreach ($this->getFactory()->createDiscounts() as $discount) {
            $names[] = $discount->name();
        }

        return $names;
    }
}
