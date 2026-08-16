<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Reporting;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Reporting\Domain\RevenueReportCalculator;

/**
 * @extends AbstractFactory<ReportingConfig>
 */
final class ReportingFactory extends AbstractFactory
{
    public function createRevenueReportCalculator(): RevenueReportCalculator
    {
        return new RevenueReportCalculator(
            $this->getBillingFacade(),
            $this->getCustomerFacade(),
        );
    }

    public function topCustomerLimit(): int
    {
        return $this->getConfig()->topCustomerLimit();
    }

    private function getBillingFacade(): BillingFacade
    {
        /** @var BillingFacade $facade */
        $facade = $this->getProvidedDependency(ReportingProvider::BILLING_FACADE);

        return $facade;
    }

    private function getCustomerFacade(): CustomerFacade
    {
        /** @var CustomerFacade $facade */
        $facade = $this->getProvidedDependency(ReportingProvider::CUSTOMER_FACADE);

        return $facade;
    }
}
