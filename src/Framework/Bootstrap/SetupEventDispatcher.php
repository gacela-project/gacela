<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Gacela\Framework\Event\Dispatcher\CompositeEventDispatcher;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;

/**
 * The one place a dispatcher is derived from a setup.
 *
 * `SetupMerger` used to build a second one of its own, registering the incoming
 * listeners by hand -- which is how the merged listeners stopped being visible
 * to anything reading the setup, and how a supplied dispatcher got replaced.
 */
final class SetupEventDispatcher
{
    public static function getDispatcher(SetupGacela $setupGacela): EventDispatcherInterface
    {
        $supplied = $setupGacela->getSuppliedEventDispatcher();

        if (!$setupGacela->canCreateEventDispatcher()) {
            // A supplied dispatcher takes precedence over
            // `disableEventListeners()`: the switch governs the dispatcher
            // Gacela would *build*, and this is one it does not build.
            return $supplied ?? new NullEventDispatcher();
        }

        $configured = new ConfigurableEventDispatcher();
        $configured->registerGenericListeners($setupGacela->getGenericListeners() ?? []);

        foreach ($setupGacela->getSpecificListeners() ?? [] as $event => $listeners) {
            foreach ($listeners as $callable) {
                $configured->registerSpecificListener($event, $callable);
            }
        }

        if (!$supplied instanceof EventDispatcherInterface) {
            return $configured;
        }

        return new CompositeEventDispatcher($configured, $supplied);
    }
}
