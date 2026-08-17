<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Money;

/**
 * Asked for in a pillar's *constructor*, which is the one seam the class
 * resolver fills and the container's lazy registry does not reach.
 */
interface CurrencyInterface
{
    public function code(): string;
}
