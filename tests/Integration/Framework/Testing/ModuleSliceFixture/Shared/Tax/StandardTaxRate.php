<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Tax;

final class StandardTaxRate implements TaxRateInterface
{
    public function basisPoints(): int
    {
        return 2100;
    }
}
