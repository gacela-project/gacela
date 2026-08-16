<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain;

use Gacela\Framework\Attribute\Inject;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\AbstractGateway;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\Method\PaymentMethodHandlerInterface;

final class PaymentProcessor
{
    /**
     * Nothing binds `LedgerInterface`, and nothing has to: the attribute names
     * the implementation at the one place that cares which it is, so the
     * application-wide wiring stays about the things more than one module
     * shares.
     */
    public function __construct(
        #[Inject(InMemoryLedger::class)] private readonly LedgerInterface $ledger,
    ) {
    }

    public function capture(
        AbstractGateway $gateway,
        PaymentMethodHandlerInterface $handler,
        AttemptId $attempt,
        string $invoiceNumber,
        int $amountCents,
    ): PaymentReceipt {
        $receipt = $gateway->capture($invoiceNumber, $amountCents)
            ->withMethod($handler->method())
            ->withAttemptId($attempt->value());

        $this->ledger->record($receipt);

        return $receipt;
    }

    /**
     * @return list<string>
     */
    public function ledgerEntries(): array
    {
        return $this->ledger->entries();
    }
}
