<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax;

use function intdiv;

final class StandardVatCalculator implements TaxCalculatorInterface
{
    public function __construct(
        private readonly TaxRateTable $rates,
    ) {
    }

    public function taxFor(int $netCents, int $taxSoFarCents, string $countryCode): int
    {
        return intdiv($netCents * $this->rates->standardRateBasisPoints(), 10_000);
    }
}
