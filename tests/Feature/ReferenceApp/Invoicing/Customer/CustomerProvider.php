<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Customer;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\Domain\CustomerRepository;

/**
 * @extends AbstractProvider<CustomerConfig>
 */
final class CustomerProvider extends AbstractProvider
{
    public const REPOSITORY = 'CUSTOMER_REPOSITORY';

    /**
     * One store for the whole process. The registration is lazy, so a request
     * that never touches a customer never builds it.
     */
    #[Provides(self::REPOSITORY)]
    public function repository(): CustomerRepository
    {
        return new CustomerRepository();
    }
}
