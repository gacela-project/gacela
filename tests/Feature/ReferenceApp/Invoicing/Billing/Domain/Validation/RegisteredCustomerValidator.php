<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation;

use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;

use function sprintf;

/**
 * The one validator that needs another module, so it is the one this module
 * adds to the tag itself rather than declaring app-wide: the two validators
 * above are about invoices and belong to anyone, this one is Billing's.
 */
final class RegisteredCustomerValidator implements InvoiceValidatorInterface
{
    public function __construct(
        private readonly CustomerFacade $customers,
    ) {
    }

    public function name(): string
    {
        return 'registered-customer';
    }

    public function reasonToRefuse(string $customerReference, int $netCents): ?string
    {
        if ($this->customers->isRegistered($customerReference)) {
            return null;
        }

        return sprintf('customer "%s" is not registered', $customerReference);
    }
}
