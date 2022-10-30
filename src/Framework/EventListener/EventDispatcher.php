<?php

declare(strict_types=1);

namespace Gacela\Framework\EventListener;

use function get_class;
use function is_callable;

final class EventDispatcher implements EventDispatcherInterface
{
    /** @var array<callable> */
    private array $genericListeners = [];

    /** @var array<class-string,list<callable>> */
    private array $listenersPerEvent = [];

    public function __construct()
    {
    }

    /**
     * @param list<callable> $genericListeners
     */
    public function registerGenericListeners(array $genericListeners): void
    {
        $this->genericListeners = $genericListeners;
    }
    /**
     * @param class-string $event
     */
    public function registerSpecificListener(string $event, callable $listener): void
    {
        $this->listenersPerEvent[$event][] = $listener;
    }

    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }

    public function dispatch(object $event): void
    {
        foreach ($this->genericListeners as $listener) {
            $this->notifyListener($listener, $event);
        }

        foreach ($this->listenersPerEvent[get_class($event)] ?? [] as $listener) {
            $this->notifyListener($listener, $event);
        }
    }

    private function notifyListener(callable $listener, object $event): void
    {
        /** @psalm-suppress MixedAssignment */
        $result = $listener($event);
        if (is_callable($result)) {
            $result($event);
        }
    }
}
