<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginAttribute\Checkout;

use Gacela\Framework\Attribute\Plugin;

#[Plugin(Discount::class)]
final class Coupon implements Discount
{
    public function name(): string
    {
        return 'coupon';
    }
}
