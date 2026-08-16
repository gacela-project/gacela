<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain;

final class PaymentReceipt
{
    public function __construct(
        public readonly string $invoiceNumber,
        public readonly int $amountCents,
        public readonly string $endpoint,
        public readonly string $method = '',
        public readonly string $attemptId = '',
    ) {
    }

    public function withMethod(string $method): self
    {
        return new self($this->invoiceNumber, $this->amountCents, $this->endpoint, $method, $this->attemptId);
    }

    public function withAttemptId(string $attemptId): self
    {
        return new self($this->invoiceNumber, $this->amountCents, $this->endpoint, $this->method, $attemptId);
    }
}
