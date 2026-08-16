<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain;

use function array_key_exists;

final class InvoiceRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    public function save(InvoiceRecord $invoice): void
    {
        $this->rows[$invoice->getNumber()] = $invoice->toArray();
    }

    public function find(string $number): ?InvoiceRecord
    {
        if (!array_key_exists($number, $this->rows)) {
            return null;
        }

        return InvoiceRecord::fromArray($this->rows[$number]);
    }

    /**
     * @return list<InvoiceRecord>
     */
    public function all(): array
    {
        $invoices = [];

        foreach ($this->rows as $row) {
            $invoices[] = InvoiceRecord::fromArray($row);
        }

        return $invoices;
    }
}
