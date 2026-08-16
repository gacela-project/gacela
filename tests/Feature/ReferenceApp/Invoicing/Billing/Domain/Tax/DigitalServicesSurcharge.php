<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax;

use function intdiv;

/**
 * Added to the stack by `gacela-prod.php` only: the surcharge is a production
 * obligation, and a developer issuing test invoices should not have to know
 * about it.
 */
final class DigitalServicesSurcharge implements TaxCalculatorInterface
{
    public function __construct(
        private readonly TaxRateTable $rates,
    ) {
    }

    public function taxFor(int $netCents, int $taxSoFarCents, string $countryCode): int
    {
        return intdiv($netCents * $this->rates->digitalSurchargeBasisPoints(), 10_000);
    }
}
