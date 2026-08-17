<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PublicApiFixture\Reporting;

use GacelaTest\Integration\PublicApiFixture\Billing\PublishedInvoice;

/**
 * Both halves of the check in one class: the name is written at the call site
 * (`new`) and a method is called on a type the call site never names again.
 */
final class ReadsThePublishedInvoice
{
    public function __construct(
        private readonly PublishedInvoice $invoice,
    ) {
    }

    public function report(): string
    {
        return $this->invoice->number();
    }

    public function build(): PublishedInvoice
    {
        return new PublishedInvoice();
    }
}
