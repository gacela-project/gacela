<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\Channel;

use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\NotificationMessage;

use function sprintf;

final class EmailChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'email';
    }

    public function deliver(NotificationMessage $message): string
    {
        return sprintf('email:%s:%s', $message->recipient, $message->subject);
    }
}
