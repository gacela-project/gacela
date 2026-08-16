<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation;

final class PositiveAmountValidator implements InvoiceValidatorInterface
{
    public function name(): string
    {
        return 'positive-amount';
    }

    public function reasonToRefuse(string $customerReference, int $netCents): ?string
    {
        return $netCents > 0 ? null : 'an invoice must be for a positive amount';
    }
}
