<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Customer;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Attribute\Cacheable;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\Domain\CustomerProfile;

/**
 * @extends AbstractFacade<CustomerFactory>
 */
final class CustomerFacade extends AbstractFacade
{
    public function registerCustomer(string $reference, string $name, string $countryCode, ?string $tier = null): void
    {
        $this->getFactory()->createCustomerDirectory()->register($reference, $name, $countryCode, $tier);
    }

    /**
     * Billing asks for the same customer several times while issuing one
     * invoice, so the lookup is cached per reference. The key template is what
     * makes each customer its own entry -- without it, one stored result would
     * answer for everyone.
     */
    #[Cacheable(ttl: 300, key: 'customer:{0}')]
    public function findCustomer(string $reference): CustomerProfile
    {
        return $this->cached(
            fn (): CustomerProfile => $this->getFactory()->createCustomerDirectory()->profileOf($reference),
        );
    }

    public function isRegistered(string $reference): bool
    {
        return $this->getFactory()->createCustomerDirectory()->isRegistered($reference);
    }

    /**
     * How many times the store behind the cache was read.
     */
    public function repositoryReadCount(): int
    {
        return $this->getFactory()->createCustomerDirectory()->readCount();
    }
}
