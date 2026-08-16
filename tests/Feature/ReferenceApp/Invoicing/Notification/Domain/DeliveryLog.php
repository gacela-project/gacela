<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain;

/**
 * What was delivered, in order. Registered once per process by the Provider, so
 * a caller that issued three invoices can read back the six notifications they
 * caused.
 */
final class DeliveryLog
{
    /** @var list<string> */
    private array $receipts = [];

    public function record(string $receipt): void
    {
        $this->receipts[] = $receipt;
    }

    /**
     * @return list<string>
     */
    public function receipts(): array
    {
        return $this->receipts;
    }
}
