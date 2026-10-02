<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

use Closure;

use function is_a;

/**
 * The dispatcher a module gets from `getProvidedDependency(EventDispatcherInterface::class)`:
 * the application's, plus the `#[AsListener]` methods.
 *
 * Only modules dispatch through it. The framework's own dispatch sites keep
 * asking the application's dispatcher, so a resolution guard costs what it
 * did, and the listeners are looked for on the first event a module asks about.
 */
final class ApplicationEventDispatcher implements EventDispatcherInterface
{
    /** @var list<array{0: class-string, 1: callable}>|null */
    private ?array $listeners = null;

    /** @var array<class-string, list<callable>> */
    private array $applicableListeners = [];

    /**
     * @param Closure(): list<array{0: class-string, 1: callable}> $source
     */
    public function __construct(
        private readonly EventDispatcherInterface $inner,
        private readonly Closure $source,
    ) {
    }

    public function hasListeners(string $eventClass): bool
    {
        if ($this->inner->hasListeners($eventClass)) {
            return true;
        }

        return $this->listenersFor($eventClass) !== [];
    }

    /**
     * The application's listeners first, asked the way
     * {@see CompositeEventDispatcher} asks a supplied dispatcher.
     */
    public function dispatch(object $event): void
    {
        if ($this->inner->hasListeners($event::class)) {
            $this->inner->dispatch($event);
        }

        foreach ($this->listenersFor($event::class) as $listener) {
            $listener($event);
        }
    }

    /**
     * Matched by inheritance, like a listener in `gacela.php`.
     *
     * @param class-string $eventClass
     *
     * @return list<callable>
     */
    private function listenersFor(string $eventClass): array
    {
        if (isset($this->applicableListeners[$eventClass])) {
            return $this->applicableListeners[$eventClass];
        }

        $this->listeners ??= ($this->source)();

        $applicable = [];
        foreach ($this->listeners as [$target, $listener]) {
            if (is_a($eventClass, $target, true)) {
                $applicable[] = $listener;
            }
        }

        return $this->applicableListeners[$eventClass] = $applicable;
    }
}
