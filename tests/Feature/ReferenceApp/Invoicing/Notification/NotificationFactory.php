<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Plugins\PluginStack;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Event\InvoiceIssuedEvent;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\Channel\NotificationChannelInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\DeliveryLog;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\NotificationDispatcher;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\NotificationMessage;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Resilience\RetryPolicyInterface;

/**
 * @extends AbstractFactory<NotificationConfig>
 */
final class NotificationFactory extends AbstractFactory
{
    /**
     * The retry policy arrives by constructor injection, so this module gets
     * the application-wide default while Payment gets a stricter one through a
     * contextual binding -- neither module knows about the other's choice.
     */
    public function __construct(
        private readonly RetryPolicyInterface $retryPolicy,
    ) {
    }

    public function createDispatcher(): NotificationDispatcher
    {
        return new NotificationDispatcher(
            $this->createChannels(),
            $this->getDeliveryLog(),
            $this->getHeaders(),
        );
    }

    public function getDeliveryLog(): DeliveryLog
    {
        /** @var DeliveryLog $log */
        $log = $this->getProvidedDependency(NotificationProvider::DELIVERY_LOG);

        return $log;
    }

    public function retryAttempts(): int
    {
        return $this->retryPolicy->attempts();
    }

    public function subjectFor(string $invoiceNumber): string
    {
        return $this->getConfig()->subjectPrefix() . ' ' . $invoiceNumber;
    }

    /**
     * Billing's event, as this module's own message.
     *
     * The one place the two vocabularies meet, and it belongs here: the subject
     * is Notification's (its prefix is this module's configuration), while the
     * recipient and the amount are facts the event carried. Nothing else in the
     * module knows an event was involved.
     */
    public function messageForInvoiceIssued(InvoiceIssuedEvent $event): NotificationMessage
    {
        return new NotificationMessage(
            $event->customerName(),
            $this->subjectFor($event->invoiceNumber()),
            $event->amountDue(),
        );
    }

    /**
     * @return PluginStack<NotificationChannelInterface>
     */
    private function createChannels(): PluginStack
    {
        return $this->getPluginStack(NotificationChannelInterface::class);
    }

    /**
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        /** @var array<string, string> $headers */
        $headers = $this->getProvidedDependency(NotificationProvider::HEADERS);

        return $headers;
    }
}
