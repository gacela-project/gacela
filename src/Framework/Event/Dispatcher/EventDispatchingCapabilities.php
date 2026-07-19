<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\GacelaEventInterface;

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
        return Config::getEventDispatcher()->hasListeners($eventClass);
    }

    private static function dispatchEvent(GacelaEventInterface $event): void
    {
        Config::getEventDispatcher()->dispatch($event);
    }
}
