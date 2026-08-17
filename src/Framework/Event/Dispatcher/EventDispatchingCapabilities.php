<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

use Gacela\Framework\Event\GacelaEventInterface;

/**
 * How the *framework* dispatches: a static reach into
 * {@see EventDispatcherProvider}, because the classes below the resolver have
 * nowhere to inject a dependency from -- they are what the injection is built
 * out of.
 *
 * @internal An application dispatches its own events through the dispatcher
 *   instead, injected like any other dependency:
 *   `getProvidedDependency(EventDispatcherInterface::class)`. That is the
 *   documented seam (`docs/events.md`, "Your own events"), it is testable
 *   without global state, and it is the one this trait is deliberately not.
 *   Both `use` sites and both methods are private to the framework and may
 *   change with any release.
 */
trait EventDispatchingCapabilities
{
    /**
     * Guard every dispatchEvent() call with this check so the event object
     * is only allocated when a listener is actually registered for it.
     *
     * @param class-string<GacelaEventInterface> $eventClass
     */
    private static function shouldDispatch(string $eventClass): bool
    {
        return EventDispatcherProvider::get()->hasListeners($eventClass);
    }

    private static function dispatchEvent(GacelaEventInterface $event): void
    {
        EventDispatcherProvider::get()->dispatch($event);
    }
}
