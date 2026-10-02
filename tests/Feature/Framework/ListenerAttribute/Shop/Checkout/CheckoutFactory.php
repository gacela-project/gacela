<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListenerAttribute\Shop\Checkout;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;

final class CheckoutFactory extends AbstractFactory
{
    public function createOrderPlacer(): OrderPlacer
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getProvidedDependency(EventDispatcherInterface::class);

        return new OrderPlacer($dispatcher);
    }
}
