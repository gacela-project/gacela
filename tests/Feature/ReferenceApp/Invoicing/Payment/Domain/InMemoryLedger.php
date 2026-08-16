<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain;

use function sprintf;

final class InMemoryLedger implements LedgerInterface
{
    /** @var list<string> */
    private array $entries = [];

    public function record(PaymentReceipt $receipt): void
    {
        $this->entries[] = sprintf('%s:%d:%s', $receipt->invoiceNumber, $receipt->amountCents, $receipt->method);
    }

    /**
     * @return list<string>
     */
    public function entries(): array
    {
        return $this->entries;
    }
}
