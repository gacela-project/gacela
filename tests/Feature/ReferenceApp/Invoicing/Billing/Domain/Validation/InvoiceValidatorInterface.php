<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation;

interface InvoiceValidatorInterface
{
    public function name(): string;

    /**
     * @return string|null what is wrong with the request, or null when nothing is
     */
    public function reasonToRefuse(string $customerReference, int $netCents): ?string;
}
