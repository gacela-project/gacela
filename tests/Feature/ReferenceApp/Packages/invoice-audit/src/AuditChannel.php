<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Packages\InvoiceAudit;

use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\Channel\NotificationChannelInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\NotificationMessage;

use function sprintf;

/**
 * A delivery channel the application never registered.
 *
 * It implements the extension point the `Notification` module publishes, and
 * the package's `config/gacela.php` puts it on the stack. Everything else --
 * the header the application appends with `extendService()`, the order of the
 * receipts -- it gets for free by being on the stack like any other channel.
 */
final class AuditChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'audit';
    }

    public function deliver(NotificationMessage $message): string
    {
        return sprintf('audit:%s:%s', $message->recipient, $message->subject);
    }
}
