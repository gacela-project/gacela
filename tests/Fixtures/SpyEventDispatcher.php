<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures;

use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;

use function array_values;

final class SpyEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $dispatched = [];

    public function __construct(
        private readonly bool $hasListeners = true,
    ) {
    }

    public function dispatch(object $event): void
    {
        $this->dispatched[] = $event;
    }

    public function hasListeners(string $eventClass): bool
    {
        return $this->hasListeners;
    }

    /**
     * @return list<object>
     */
    public function dispatchedEvents(): array
    {
        return $this->dispatched;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $eventClass
     *
     * @return list<T>
     */
    public function dispatchedEventsOf(string $eventClass): array
    {
        return array_values(
            array_filter(
                $this->dispatched,
                static fn (object $event): bool => $event instanceof $eventClass,
            ),
        );
    }
}
