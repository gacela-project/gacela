<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

/**
 * The listeners the configuration registered and a dispatcher the application
 * supplied, as one dispatcher.
 *
 * `setEventDispatcher()` says what *delivers* events; `registerGenericListener()`
 * and `registerSpecificListener()` say what must *run*. Both are things the
 * application asked for, so both happen. Before this, asking for both meant
 * losing one of them: a setup that brought listeners had a fresh
 * `ConfigurableEventDispatcher` installed to hold them, and since that class is
 * `final`, an application's own dispatcher could never be the one kept -- it
 * was simply dropped.
 *
 * Order: the configured listeners first, the supplied dispatcher last. The
 * supplied one is a *destination* -- the bridge onto the application's own bus --
 * so everything the configuration declared has observed the event before it
 * leaves Gacela, and the order among the configured listeners stays the
 * registration order it always was.
 *
 * Only built when a dispatcher was actually supplied: with none, the
 * configured dispatcher is returned as-is and the hot-path guard is the single
 * array lookup it has always been.
 */
final class CompositeEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $configured,
        private readonly EventDispatcherInterface $supplied,
    ) {
    }

    /**
     * True when *either* side would act.
     *
     * Every dispatch site guards on this before allocating its event, so
     * answering from the configured listeners alone would silently skip the
     * events the supplied dispatcher asked for -- the same failure this class
     * exists to end, one level down.
     */
    public function hasListeners(string $eventClass): bool
    {
        if ($this->configured->hasListeners($eventClass)) {
            return true;
        }

        return $this->supplied->hasListeners($eventClass);
    }

    /**
     * The supplied dispatcher is asked again rather than told unconditionally:
     * `docs/events.md` promises that returning false from `hasListeners()` is
     * how it goes quiet, and a configured listener interested in the same event
     * must not smuggle it through. The configured side needs no such question --
     * it resolves its own applicable listeners, and dispatching to none of them
     * is a memo lookup.
     */
    public function dispatch(object $event): void
    {
        $this->configured->dispatch($event);

        if ($this->supplied->hasListeners($event::class)) {
            $this->supplied->dispatch($event);
        }
    }
}
