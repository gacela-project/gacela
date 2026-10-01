<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginAttribute\Checkout;

use Gacela\Framework\Attribute\Plugin as JoinsStack;

#[JoinsStack(Discount::class)]
final class Aliased implements Discount
{
    public function name(): string
    {
        return 'aliased';
    }
}
