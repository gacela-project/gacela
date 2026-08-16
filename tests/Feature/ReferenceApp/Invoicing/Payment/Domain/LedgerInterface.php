<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain;

interface LedgerInterface
{
    public function record(PaymentReceipt $receipt): void;

    /**
     * @return list<string>
     */
    public function entries(): array;
}
