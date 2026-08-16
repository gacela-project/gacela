<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\InvoiceRecord;

/**
 * @extends AbstractFacade<BillingFactory>
 */
final class BillingFacade extends AbstractFacade
{
    public function issueInvoice(string $customerReference, int $netCents): InvoiceRecord
    {
        return $this->getFactory()->createInvoiceIssuer()->issue($customerReference, $netCents);
    }

    public function findInvoice(string $number): ?InvoiceRecord
    {
        return $this->getFactory()->getInvoiceRepository()->find($number);
    }

    /**
     * @return list<InvoiceRecord>
     */
    public function issuedInvoices(): array
    {
        return $this->getFactory()->getInvoiceRepository()->all();
    }

    public function currency(): string
    {
        return $this->getFactory()->currency();
    }

    public function invoiceNumberPrefix(): string
    {
        return $this->getFactory()->getNumberPrefix();
    }

    /**
     * @return list<string>
     */
    public function validatorNames(): array
    {
        return $this->getFactory()->createInvoiceIssuer()->validatorNames();
    }
}
