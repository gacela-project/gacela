<?php

declare(strict_types=1);

namespace Gacela\Framework\Event;

interface EventDispatcherInterface
{
    /**
     * @param list<object> $events
     */
    public function dispatchAll(array $events): void;

    public function dispatch(object $event): void;
}
