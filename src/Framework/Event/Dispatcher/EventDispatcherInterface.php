<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

interface EventDispatcherInterface
{
    public function dispatch(object $event): void;

    /**
     * Whether any listener would receive an event of the given class.
     * Lets hot-path dispatch sites skip allocating the event when nothing listens.
     *
     * @param class-string $eventClass
     */
    public function hasListeners(string $eventClass): bool;
}
