<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\GacelaEventInterface;

trait EventDispatchingCapabilities
{
    private static function dispatchEvent(GacelaEventInterface $event): void
    {
        Config::getEventDispatcher()->dispatch($event);
    }
}
