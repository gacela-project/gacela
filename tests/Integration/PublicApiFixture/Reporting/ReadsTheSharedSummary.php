<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PublicApiFixture\Reporting;

use GacelaTest\Integration\PublicApiFixture\Billing\Shared\InvoiceSummary;

/**
 * The convention half, with no annotation anywhere: Billing publishes its
 * `Shared\` sub-namespace by living in it.
 */
final class ReadsTheSharedSummary
{
    public function __construct(
        private readonly InvoiceSummary $summary,
    ) {
    }

    public function report(): int
    {
        return $this->summary->total();
    }

    public function build(): InvoiceSummary
    {
        return new InvoiceSummary();
    }
}
