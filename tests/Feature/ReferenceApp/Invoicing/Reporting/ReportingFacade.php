<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Reporting;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Reporting\Domain\RevenueReport;

/**
 * @extends AbstractFacade<ReportingFactory>
 */
final class ReportingFacade extends AbstractFacade
{
    public function revenueReport(): RevenueReport
    {
        return $this->getFactory()->createRevenueReportCalculator()->calculate();
    }

    public function topCustomerLimit(): int
    {
        return $this->getFactory()->topCustomerLimit();
    }
}
