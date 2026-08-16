<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Packaging;

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\Channel\NotificationChannelInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\Channel\WebhookChannel;

/**
 * Customers get the webhook only where there is something on the other end of
 * it, so a developer issuing test invoices sees the email channel alone.
 */
final class ProductionNotificationChannels
{
    public function __invoke(GacelaConfig $config): void
    {
        $config->addPluginStack(NotificationChannelInterface::class, [WebhookChannel::class]);
    }
}
