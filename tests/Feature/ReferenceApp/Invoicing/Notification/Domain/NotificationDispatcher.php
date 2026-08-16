<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain;

use Gacela\Framework\Plugins\PluginStack;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\Channel\NotificationChannelInterface;

final class NotificationDispatcher
{
    /**
     * @param PluginStack<NotificationChannelInterface> $channels
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly PluginStack $channels,
        private readonly DeliveryLog $log,
        private readonly array $headers,
    ) {
    }

    /**
     * @return list<string> one receipt per channel, in declaration order
     */
    public function dispatch(NotificationMessage $message): array
    {
        $stamped = $message->withHeaders($this->headers);
        $receipts = [];

        foreach ($this->channels as $channel) {
            $receipt = $channel->deliver($stamped);
            $this->log->record($receipt);
            $receipts[] = $receipt;
        }

        return $receipts;
    }

    /**
     * @return list<string>
     */
    public function channelNames(): array
    {
        $names = [];

        foreach ($this->channels as $channel) {
            $names[] = $channel->name();
        }

        return $names;
    }
}
