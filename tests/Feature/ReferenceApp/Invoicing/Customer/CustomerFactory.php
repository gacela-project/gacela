<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Customer;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\Domain\CustomerDirectory;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\Domain\CustomerRepository;

/**
 * @extends AbstractFactory<CustomerConfig>
 */
final class CustomerFactory extends AbstractFactory
{
    public function createCustomerDirectory(): CustomerDirectory
    {
        return new CustomerDirectory(
            $this->getCustomerRepository(),
            $this->getConfig()->defaultTier(),
        );
    }

    private function getCustomerRepository(): CustomerRepository
    {
        /** @var CustomerRepository $repository */
        $repository = $this->getProvidedDependency(CustomerProvider::REPOSITORY);

        return $repository;
    }
}
