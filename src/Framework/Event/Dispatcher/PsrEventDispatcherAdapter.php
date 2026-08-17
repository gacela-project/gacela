<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

/**
 * A PSR-14 dispatcher, seen as a Gacela one.
 *
 * This is what lets `setEventDispatcher()` accept the bus a host application
 * already has -- Symfony's, Laravel's, or any other PSR-14 implementation --
 * without the application writing an adapter of its own: the parameter accepts
 * either interface and wraps a PSR-14 dispatcher in this.
 *
 * The two interfaces are kept apart rather than merged. PSR-14's
 * `dispatch(object $event)` declares no return type and its contract says the
 * event comes back; Gacela's declares `: void`. A single class can satisfy both
 * signatures, and would still be a broken PSR-14 implementation -- returning
 * nothing where callers are told to expect their event. Widening Gacela's
 * interface to `: object` instead would break every custom dispatcher already
 * written against it. So each side keeps its own contract and this carries
 * events across. {@see Psr14EventDispatcher} goes the other way.
 *
 * ## What `hasListeners()` answers, and why
 *
 * True, always.
 *
 * PSR-14 offers no way to ask what is subscribed -- a bus is precisely the
 * component that knows its listeners while its callers do not.
 * `ListenerProviderInterface` cannot help either: it resolves listeners from an
 * event *instance*, and the guard's whole purpose is to answer before one is
 * allocated.
 *
 * That leaves two possible answers. `false` would make every guarded dispatch
 * site skip, so a dispatcher the application deliberately handed over would
 * receive nothing at all, and nothing would say so -- the failure #888 was, one
 * level down. `true` costs one event allocation per dispatch site that fires,
 * which is the ordinary price of listening to events, and it is paid only by an
 * application that installed a bus. An application that supplies no dispatcher
 * never constructs this class, so the hot-path guard stays the single array
 * lookup it has always been (`EventDispatchBench` is unmoved).
 *
 * An application that wants a narrower answer than "everything" has it already:
 * implement Gacela's `EventDispatcherInterface` directly and say so in
 * `hasListeners()`. That is the seam `docs/events.md` documents, and this
 * adapter is the convenience for everyone who does not need it.
 */
final class PsrEventDispatcherAdapter implements EventDispatcherInterface
{
    public function __construct(
        private readonly PsrEventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * The dispatcher an application supplied, as a Gacela one: its own if it
     * wrote one against Gacela's interface, this adapter around it if it handed
     * over a PSR-14 bus.
     *
     * A class carrying both interfaces is taken as the Gacela one. `void`
     * satisfies PSR-14's untyped `dispatch()`, so writing both is possible, and
     * Gacela's is the richer of the two -- it answers `hasListeners()` for
     * itself, which is exactly what wrapping would throw away.
     */
    public static function wrap(EventDispatcherInterface|PsrEventDispatcherInterface $dispatcher): EventDispatcherInterface
    {
        return $dispatcher instanceof EventDispatcherInterface
            ? $dispatcher
            : new self($dispatcher);
    }

    /**
     * True for every class -- see the class docblock for what PSR-14 makes
     * knowable and what it does not.
     */
    public function hasListeners(string $eventClass): bool
    {
        return true;
    }

    /**
     * PSR-14 hands back the event, possibly a different object; Gacela's
     * interface returns nothing, so the value is dropped here rather than
     * surfacing where no caller could use it.
     */
    public function dispatch(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
