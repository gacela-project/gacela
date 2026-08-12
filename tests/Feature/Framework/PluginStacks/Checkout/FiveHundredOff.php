<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginStacks\Checkout;

final class FiveHundredOff implements Discount
{
    public function apply(int $cents): int
    {
        return max(0, $cents - 500);
    }
}
