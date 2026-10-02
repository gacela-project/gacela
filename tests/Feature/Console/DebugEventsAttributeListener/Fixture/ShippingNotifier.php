<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugEventsAttributeListener\Fixture;

use Gacela\Framework\Attribute\AsListener;
use Gacela\Framework\Event\GacelaEventInterface;

final class ShippingNotifier
{
    #[AsListener]
    public function onOrderShipped(OrderShippedEvent $event): void
    {
    }

    /**
     * On the interface every Gacela event implements: it covers the project's
     * events, and never the framework's, whose dispatch sites do not read it.
     */
    #[AsListener(GacelaEventInterface::class)]
    public function onAnything(GacelaEventInterface $event): void
    {
    }
}
