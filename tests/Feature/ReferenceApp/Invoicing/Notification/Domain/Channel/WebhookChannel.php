<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\Channel;

use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\NotificationMessage;

use function implode;
use function sprintf;

final class WebhookChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'webhook';
    }

    public function deliver(NotificationMessage $message): string
    {
        return sprintf('webhook:%s:%s', $message->recipient, implode(',', array_keys($message->headers)));
    }
}
