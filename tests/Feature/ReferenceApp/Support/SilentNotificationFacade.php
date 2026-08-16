<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Support;

use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\NotificationFacade;
use Override;

/**
 * The notification module, replaced.
 *
 * Handed to `Gacela::overrideExistingResolvedClass()`, this is what every
 * consumer of `NotificationFacade` gets -- including Billing, which reaches it
 * through a `#[ServiceMap]` accessor and never learns the difference.
 */
final class SilentNotificationFacade extends NotificationFacade
{
    /** @var list<string> */
    private array $suppressed = [];

    /**
     * @return list<string>
     */
    #[Override]
    public function notifyInvoiceIssued(string $recipient, string $invoiceNumber, string $body): array
    {
        $this->suppressed[] = $invoiceNumber;

        return [];
    }

    /**
     * @return list<string>
     */
    public function suppressed(): array
    {
        return $this->suppressed;
    }
}
