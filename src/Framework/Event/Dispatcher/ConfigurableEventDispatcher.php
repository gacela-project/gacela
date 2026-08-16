<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

use function is_a;

/**
 * @psalm-type SpecificListenersMap = array<class-string, list<callable>>
 */
final class ConfigurableEventDispatcher implements EventDispatcherInterface
{
    /** @var list<callable> */
    private array $genericListeners = [];

    /** @var SpecificListenersMap */
    private array $specificListeners = [];

    /**
     * What runs for a concrete event class, worked out once and kept.
     *
     * @var array<class-string, list<callable>>
     */
    private array $applicableListeners = [];

    /**
     * Adds to the listeners already registered, the way
     * {@see registerSpecificListener()} does.
     *
     * It used to assign. `SetupMerger` merges one setup's listeners into
     * another's by calling this, and appends the specific ones in the very
     * next lines -- so combining a bootstrap closure with a `gacela.php`
     * replaced the generic listeners of whichever side was merged into, or
     * wiped them to none when the other side had registered any. The listener
     * was registered, nothing failed, and it never fired.
     *
     * The other caller, {@see \Gacela\Framework\Bootstrap\SetupEventDispatcher},
     * builds a fresh dispatcher, where appending and assigning are the same
     * thing.
     *
     * @param list<callable> $genericListeners
     */
    public function registerGenericListeners(array $genericListeners): void
    {
        foreach ($genericListeners as $listener) {
            $this->genericListeners[] = $listener;
        }

        $this->applicableListeners = [];
    }

    /**
     * The listener runs for that event class and for every event that extends
     * or implements it -- `AbstractGacelaClassResolverEvent::class` covers all
     * of the resolver events, `GacelaEventInterface::class` covers everything.
     *
     * @param class-string $event
     */
    public function registerSpecificListener(string $event, callable $listener): void
    {
        $this->specificListeners[$event][] = $listener;

        $this->applicableListeners = [];
    }

    /**
     * The hot guard: every dispatch site asks this before allocating its event,
     * so it is answered from the memo -- one array lookup, whatever the shape of
     * the hierarchy behind it.
     */
    public function hasListeners(string $eventClass): bool
    {
        return ($this->applicableListeners[$eventClass] ??= $this->resolveListenersFor($eventClass)) !== [];
    }

    public function dispatch(object $event): void
    {
        $listeners = $this->applicableListeners[$event::class] ??= $this->resolveListenersFor($event::class);

        foreach ($listeners as $listener) {
            $listener($event);
        }
    }

    /**
     * Walked once per concrete event class. `is_a()` with `allow_string` takes
     * the class name, which is what the guard has: the event itself is not
     * allocated yet, and the whole point of the guard is that it may never be.
     *
     * @param class-string $eventClass
     *
     * @return list<callable>
     */
    private function resolveListenersFor(string $eventClass): array
    {
        $listeners = $this->genericListeners;

        foreach ($this->specificListeners as $target => $targetListeners) {
            if (is_a($eventClass, $target, true)) {
                foreach ($targetListeners as $listener) {
                    $listeners[] = $listener;
                }
            }
        }

        return $listeners;
    }
}
