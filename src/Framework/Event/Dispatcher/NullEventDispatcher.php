<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

final class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatchAll(array $events): void
    {
    }

    public function dispatch(object $event): void
    {
    }
}
