<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListenerAttribute\Shop\Checkout;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<CheckoutFactory>
 */
final class CheckoutFacade extends AbstractFacade
{
    public function placeOrder(int $id): void
    {
        $this->getFactory()->createOrderPlacer()->place($id);
    }

    public function isOrderPlacedListenedTo(): bool
    {
        return $this->getFactory()->createOrderPlacer()->isListenedTo();
    }
}
