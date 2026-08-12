<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginStacks\Checkout;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Plugins\PluginStack;

final class CheckoutFactory extends AbstractFactory
{
    /**
     * @return PluginStack<Discount>
     */
    public function createDiscounts(): PluginStack
    {
        return $this->getPluginStack(Discount::class);
    }
}
