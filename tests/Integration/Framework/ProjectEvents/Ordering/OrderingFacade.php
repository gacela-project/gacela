<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ProjectEvents\Ordering;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<OrderingFactory>
 */
final class OrderingFacade extends AbstractFacade
{
    public function placeOrder(string $reference): void
    {
        $this->getFactory()->createOrderPlacer()->place($reference);
    }
}
