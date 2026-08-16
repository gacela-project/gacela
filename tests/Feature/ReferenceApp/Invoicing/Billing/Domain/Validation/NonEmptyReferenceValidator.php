<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation;

final class NonEmptyReferenceValidator implements InvoiceValidatorInterface
{
    public function name(): string
    {
        return 'non-empty-reference';
    }

    public function reasonToRefuse(string $customerReference, int $netCents): ?string
    {
        return $customerReference === '' ? 'an invoice must name a customer' : null;
    }
}
