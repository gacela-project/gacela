<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Event\InvoiceIssuedEvent;

/**
 * Not `final`, unlike every other class here: this is the one seam a caller is
 * expected to replace. `Gacela::overrideExistingResolvedClass()` hands the
 * resolver an instance, and a test that wants to issue an invoice without
 * sending anything subclasses this facade to do it.
 *
 * @api the extension point is the point; without this Psalm reports the class
 *      as one that should have been final, having found no subclass inside the
 *      application
 *
 * @extends AbstractFacade<NotificationFactory>
 */
class NotificationFacade extends AbstractFacade
{
    /**
     * This module's reaction to an invoice being issued.
     *
     * The subscriber names the event; the publisher names nobody. Billing
     * dispatches `InvoiceIssuedEvent` and has no idea this method exists --
     * `gacela.php` is where the two are wired together, and a second reaction to
     * the same event changes nothing in Billing.
     *
     * Reading the event is the Factory's job, like every other translation into
     * this module's own shapes: a Facade only delegates, which the shipped
     * `gacela.facadeOnlyDelegates` rule holds this application to.
     *
     * @return list<string> one delivery receipt per channel
     */
    public function onInvoiceIssued(InvoiceIssuedEvent $event): array
    {
        return $this->getFactory()->createDispatcher()->dispatch(
            $this->getFactory()->messageForInvoiceIssued($event),
        );
    }

    /**
     * @return list<string> every receipt this process produced, in order
     */
    public function deliveries(): array
    {
        return $this->getFactory()->getDeliveryLog()->receipts();
    }

    /**
     * @return list<string>
     */
    public function channelNames(): array
    {
        return $this->getFactory()->createDispatcher()->channelNames();
    }

    public function retryAttempts(): int
    {
        return $this->getFactory()->retryAttempts();
    }
}
