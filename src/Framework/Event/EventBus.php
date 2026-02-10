<?php

declare(strict_types=1);

namespace Gacela\Framework\Event;

use Gacela\Framework\Config\Config;

/**
 * EventBus provides a simplified interface for event-driven module communication.
 * Use this for decoupled communication between modules.
 */
final class EventBus
{
    /**
     * Dispatch an event to all registered listeners.
     *
     * @param object $event The event to dispatch
     */
    public static function dispatch(object $event): void
    {
        Config::getEventDispatcher()->dispatch($event);
    }

    /**
     * Register a listener for a specific event type.
     *
     * @template T of object
     *
     * @param class-string<T> $eventClass
     * @param callable(T):void $listener
     */
    public static function listen(string $eventClass, callable $listener): void
    {
        $dispatcher = Config::getEventDispatcher();

        if (method_exists($dispatcher, 'registerSpecificListener')) {
            /** @var \Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher $dispatcher */
            $dispatcher->registerSpecificListener($eventClass, $listener);
        }
    }

    /**
     * Reset the cached dispatcher (mainly for testing).
     *
     * @internal
     */
    public static function resetCache(): void
    {
        // Delegate to Config's reset
        Config::resetInstance();
    }
}
