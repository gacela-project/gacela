<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PublicApiFixture\Reporting;

use GacelaTest\Integration\PublicApiFixture\Billing\Domain\InvoiceRepository;

/**
 * Nothing published, nothing exempt: the precondition every silent assertion in
 * these tests rests on.
 */
final class ReadsTheRepository
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
    ) {
    }

    /**
     * @return list<string>
     */
    public function report(): array
    {
        return $this->invoices->findAll();
    }
}
