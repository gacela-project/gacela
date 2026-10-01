<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginAttribute\Checkout;

use Gacela\Framework\Attribute\Plugin;

#[Plugin(Discount::class, priority: 10)]
final class Loyalty implements Discount
{
    public function name(): string
    {
        return 'loyalty';
    }
}
