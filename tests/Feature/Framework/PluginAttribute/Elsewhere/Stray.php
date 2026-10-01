<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginAttribute\Elsewhere;

use Gacela\Framework\Attribute\Plugin;
use GacelaTest\Feature\Framework\PluginAttribute\Checkout\Discount;

#[Plugin(Discount::class)]
final class Stray implements Discount
{
    public function name(): string
    {
        return 'stray';
    }
}
