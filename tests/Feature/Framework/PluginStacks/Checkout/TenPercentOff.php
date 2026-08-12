<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginStacks\Checkout;

final class TenPercentOff implements Discount
{
    public function apply(int $cents): int
    {
        return (int)($cents * 0.9);
    }
}
