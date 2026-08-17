<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Event\InvoiceIssuedEvent;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\Channel\NotificationChannelInterface;
use GacelaTest\Feature\ReferenceApp\Packages\InvoiceAudit\AuditChannel;
use GacelaTest\Feature\ReferenceApp\Packages\InvoiceAudit\AuditTrail;

/**
 * What this package contributes to the application that installs it.
 *
 * Same shape and same surface as the application's own `gacela.php`. Two
 * contributions, both of which the application would otherwise have had to
 * write itself and keep in step by hand:
 *
 *  - a delivery channel, appended to a stack the `Notification` module declared;
 *  - a reaction to an event the `Billing` module announces.
 */
return static function (GacelaConfig $config): void {
    $config->addPluginStack(NotificationChannelInterface::class, [AuditChannel::class]);

    $config->registerSpecificListener(
        InvoiceIssuedEvent::class,
        static fn (InvoiceIssuedEvent $event): null => AuditTrail::record(
            \sprintf('%s %d %s', $event->invoiceNumber(), $event->grossCents(), $event->currency()),
        ),
    );
};
