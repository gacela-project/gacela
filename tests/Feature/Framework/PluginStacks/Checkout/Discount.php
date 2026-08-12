<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginStacks\Checkout;

interface Discount
{
    public function apply(int $cents): int;
}
