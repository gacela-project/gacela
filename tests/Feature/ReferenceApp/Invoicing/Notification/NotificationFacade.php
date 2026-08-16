<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\NotificationMessage;

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
     * @return list<string> one delivery receipt per channel
     */
    public function notifyInvoiceIssued(string $recipient, string $invoiceNumber, string $body): array
    {
        return $this->getFactory()->createDispatcher()->dispatch(
            new NotificationMessage($recipient, $this->getFactory()->subjectFor($invoiceNumber), $body),
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
