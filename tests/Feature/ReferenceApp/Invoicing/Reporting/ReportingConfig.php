<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Reporting;

use Gacela\Framework\AbstractConfig;

final class ReportingConfig extends AbstractConfig
{
    /**
     * How many customers a summary names before it stops. Defaulted here rather
     * than declared in the schema: a report that lists everyone is a working
     * report, so an environment that never sets this is not misconfigured.
     */
    public function topCustomerLimit(): int
    {
        return $this->getInt('reporting.top_customer_limit', 10);
    }
}
