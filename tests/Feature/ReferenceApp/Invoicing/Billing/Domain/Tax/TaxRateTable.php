<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax;

/**
 * The rates the calculators read, in basis points.
 *
 * Built once, lazily, from configuration in `gacela.php` -- so the calculators
 * take a value object rather than reaching for the config singleton, and the
 * region dimension is the only thing that decides what is in it.
 */
final class TaxRateTable
{
    public function __construct(
        private readonly int $standardRateBasisPoints,
        private readonly int $digitalSurchargeBasisPoints,
    ) {
    }

    public function standardRateBasisPoints(): int
    {
        return $this->standardRateBasisPoints;
    }

    public function digitalSurchargeBasisPoints(): int
    {
        return $this->digitalSurchargeBasisPoints;
    }
}
