<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain;

final class NotificationMessage
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $recipient,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->recipient, $this->subject, $this->body, $headers);
    }
}
