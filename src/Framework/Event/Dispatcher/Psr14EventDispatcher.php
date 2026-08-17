<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Gacela's dispatcher, seen as a PSR-14 one.
 *
 * The direction {@see PsrEventDispatcherAdapter} does not cover: a library that
 * type-hints `Psr\EventDispatcher\EventDispatcherInterface` can be handed the
 * dispatcher this application configured, listeners and all.
 *
 * ```php
 * $psr = new Psr14EventDispatcher(Config::getInstance()->getSetupGacela()->getEventDispatcher());
 * $library = new SomeLibrary($psr);
 * ```
 *
 * `ConfigurableEventDispatcher` deliberately does not implement PSR-14 itself.
 * Its `dispatch()` returns `void`, which satisfies the *signature* -- PSR-14
 * declares no return type -- while breaking the *contract*, which says the
 * event comes back. A class that is a PSR-14 dispatcher in name and returns
 * nothing is worse than one that is honestly not a PSR-14 dispatcher, so the
 * conversion is explicit and lives here.
 */
final class Psr14EventDispatcher implements PsrEventDispatcherInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Returns the event it was given, as PSR-14 requires. It is the same
     * object: Gacela's events are immutable and its listeners are notify-only,
     * so there is nothing for a listener to have replaced.
     *
     * Two conditions keep it from being dispatched at all:
     *
     * - `hasListeners()` says no. That is the promise `docs/events.md` makes
     *   about a dispatcher an application supplied -- declining a class is how
     *   it goes quiet -- and it holds whoever is doing the dispatching.
     * - PSR-14 says a dispatcher handed an already-stopped event must return
     *   immediately. Propagation cannot be stopped *between* Gacela's own
     *   listeners, which are notify-only; honouring an event that arrives
     *   stopped is the part that costs nothing and is simply correct.
     */
    public function dispatch(object $event): object
    {
        if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
            return $event;
        }

        if ($this->dispatcher->hasListeners($event::class)) {
            $this->dispatcher->dispatch($event);
        }

        return $event;
    }
}
