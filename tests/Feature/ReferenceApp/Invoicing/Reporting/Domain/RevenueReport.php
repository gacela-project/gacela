<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Reporting\Domain;

final class RevenueReport
{
    /**
     * @param array<string, int> $grossByCustomerName
     */
    public function __construct(
        public readonly int $invoiceCount,
        public readonly int $netCents,
        public readonly int $taxCents,
        public readonly int $grossCents,
        public readonly string $currency,
        public readonly array $grossByCustomerName,
    ) {
    }
}
