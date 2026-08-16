<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

/**
 * @psalm-type SpecificListenersMap = array<class-string, list<callable>>
 */
final class ConfigurableEventDispatcher implements EventDispatcherInterface
{
    /** @var array<callable> */
    private array $genericListeners = [];

    /** @var SpecificListenersMap */
    private array $specificListeners = [];

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
    }

    /**
     * @param class-string $event
     */
    public function registerSpecificListener(string $event, callable $listener): void
    {
        $this->specificListeners[$event][] = $listener;
    }

    public function hasListeners(string $eventClass): bool
    {
        return $this->genericListeners !== []
            || isset($this->specificListeners[$eventClass]);
    }

    public function dispatch(object $event): void
    {
        foreach ($this->genericListeners as $listener) {
            $this->notifyListener($listener, $event);
        }

        foreach ($this->specificListeners[$event::class] ?? [] as $listener) {
            $this->notifyListener($listener, $event);
        }
    }

    private function notifyListener(callable $listener, object $event): void
    {
        $listener($event);
    }
}
