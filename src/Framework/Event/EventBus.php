<?php

declare(strict_types=1);

namespace Gacela\Framework\Event;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;

/**
 * EventBus provides a simplified interface for event-driven module communication.
 * Use this for decoupled communication between modules.
 */
final class EventBus
{
    private static ?EventDispatcherInterface $dispatcher = null;

    /**
     * Dispatch an event to all registered listeners.
     *
     * @param object $event The event to dispatch
     */
    public static function dispatch(object $event): void
    {
        self::getDispatcher()->dispatch($event);
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
        $config = Config::getInstance();
        $dispatcher = $config->getEventDispatcher();

        if (method_exists($dispatcher, 'registerSpecificListener')) {
            /** @var \Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher $dispatcher */
            $dispatcher->registerSpecificListener($eventClass, $listener);
        }

        self::$dispatcher = $dispatcher;
    }

    /**
     * Reset the cached dispatcher (mainly for testing).
     *
     * @internal
     */
    public static function resetCache(): void
    {
        self::$dispatcher = null;
    }

    private static function getDispatcher(): EventDispatcherInterface
    {
        if (self::$dispatcher === null) {
            $config = Config::getInstance();
            self::$dispatcher = $config->getEventDispatcher();
        }

        return self::$dispatcher;
    }
}
