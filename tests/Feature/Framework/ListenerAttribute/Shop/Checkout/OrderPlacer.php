<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListenerAttribute\Shop\Checkout;

use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\OrderPlaced;

final class OrderPlacer
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function place(int $id): void
    {
        $this->dispatcher->dispatch(new OrderPlaced($id));
    }

    public function isListenedTo(): bool
    {
        return $this->dispatcher->hasListeners(OrderPlaced::class);
    }
}
