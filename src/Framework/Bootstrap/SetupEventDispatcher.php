<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;

final class SetupEventDispatcher
{
    public static function getDispatcher(SetupGacela $setupGacela): EventDispatcherInterface
    {
        if (!$setupGacela->canCreateEventDispatcher()) {
            return new NullEventDispatcher();
        }

        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerGenericListeners($setupGacela->getGenericListeners() ?? []);

        foreach ($setupGacela->getSpecificListeners() ?? [] as $event => $listeners) {
            foreach ($listeners as $callable) {
                $dispatcher->registerSpecificListener($event, $callable);
            }
        }

        return $dispatcher;
    }
}
