<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Support;

use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Event\InvoiceIssuedEvent;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\NotificationFacade;
use Override;

/**
 * The notification module, replaced.
 *
 * Handed to `Gacela::overrideExistingResolvedClass()`, this is what every
 * consumer of `NotificationFacade` gets -- including the listener in
 * `gacela.php` that turns Billing's `InvoiceIssuedEvent` into a notification.
 * That listener resolves the facade rather than constructing one for exactly
 * this reason.
 *
 * Overriding the facade method is what keeps the real module out of the way
 * entirely: no Factory, no channels, no delivery log -- which is what a slice
 * test asserts when it says the replaced module was never built.
 */
final class SilentNotificationFacade extends NotificationFacade
{
    /** @var list<string> */
    private array $suppressed = [];

    /**
     * @return list<string>
     */
    #[Override]
    public function onInvoiceIssued(InvoiceIssuedEvent $event): array
    {
        $this->suppressed[] = $event->invoiceNumber();

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
