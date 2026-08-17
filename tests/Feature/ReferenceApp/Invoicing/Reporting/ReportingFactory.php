<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Reporting;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Reporting\Domain\RevenueReportCalculator;

/**
 * Two ways of reaching another module sit side by side here on purpose.
 *
 * The Billing facade comes through the Provider, because the ledger is what a
 * revenue report is *of* and that dependency is worth writing down. The
 * Customer facade comes through `#[ServiceMap]`, which is the shorter path for
 * the one call that turns a reference into a name -- declared as an attribute
 * rather than a `@method` docblock, so a rename moves it and
 * `migrate:service-map` has nothing left to report.
 *
 * @extends AbstractFactory<ReportingConfig>
 */
#[ServiceMap(method: 'getCustomerFacade', className: CustomerFacade::class)]
final class ReportingFactory extends AbstractFactory
{
    use ServiceResolverAwareTrait;

    public function createRevenueReportCalculator(): RevenueReportCalculator
    {
        /** @var CustomerFacade $customers */
        $customers = $this->getCustomerFacade();

        return new RevenueReportCalculator(
            $this->getBillingFacade(),
            $customers,
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
}
