<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Reporting\Domain;

use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;

use function count;

/**
 * The cross-module read, in one class.
 *
 * Two facades in, one report out: Billing hands over its invoices as the shape
 * it declared for them, and Customer answers who each reference belongs to.
 * Nothing else of either module is named here -- no repository, no factory, no
 * domain service -- which is what makes the boundary something a rule can hold.
 */
final class RevenueReportCalculator
{
    public function __construct(
        private readonly BillingFacade $billing,
        private readonly CustomerFacade $customers,
    ) {
    }

    public function calculate(): RevenueReport
    {
        $invoices = $this->billing->issuedInvoices();

        $netCents = 0;
        $taxCents = 0;
        $grossCents = 0;
        $grossByCustomerName = [];

        foreach ($invoices as $invoice) {
            $netCents += $invoice->getNetCents();
            $taxCents += $invoice->getTaxCents();
            $grossCents += $invoice->getGrossCents();

            $name = $this->customers->findCustomer($invoice->getCustomerReference())->getName();
            $grossByCustomerName[$name] = ($grossByCustomerName[$name] ?? 0) + $invoice->getGrossCents();
        }

        return new RevenueReport(
            count($invoices),
            $netCents,
            $taxCents,
            $grossCents,
            $this->billing->currency(),
            $grossByCustomerName,
        );
    }
}
