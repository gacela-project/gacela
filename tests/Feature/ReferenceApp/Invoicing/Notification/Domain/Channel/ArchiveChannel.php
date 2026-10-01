<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\Channel;

use Gacela\Framework\Attribute\Plugin;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\NotificationMessage;

use function sprintf;

/**
 * Keeps a copy of every notification, in every environment. It joins the stack
 * by its attribute alone: `gacela.php` declares the stack and never names it.
 */
#[Plugin(NotificationChannelInterface::class)]
final class ArchiveChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'archive';
    }

    public function deliver(NotificationMessage $message): string
    {
        return sprintf('archive:%s:%s', $message->recipient, $message->subject);
    }
}
