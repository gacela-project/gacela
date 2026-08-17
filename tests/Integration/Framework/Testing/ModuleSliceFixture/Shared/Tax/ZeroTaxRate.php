<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Tax;

/**
 * The replacement a slice binds in place of {@see StandardTaxRate}, and -- when
 * a test registers it under a class it is not -- the bogus double
 * `bootstrapModule()` refuses.
 */
final class ZeroTaxRate implements TaxRateInterface
{
    public function basisPoints(): int
    {
        return 0;
    }
}
