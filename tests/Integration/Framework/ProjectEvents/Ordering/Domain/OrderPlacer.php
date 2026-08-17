<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ProjectEvents\Ordering\Domain;

use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;

/**
 * The dispatcher arrives as a constructor argument, like every other
 * dependency: no trait, no static call, nothing to reset between tests.
 */
final class OrderPlacer
{
    public function __construct(
        private readonly EventDispatcherInterface $events,
    ) {
    }

    public function place(string $reference): void
    {
        // The guard the framework's own dispatch sites use, for the same
        // reason: with nothing listening, the event is never built.
        if ($this->events->hasListeners(OrderPlacedEvent::class)) {
            $this->events->dispatch(new OrderPlacedEvent($reference));
        }
    }
}
