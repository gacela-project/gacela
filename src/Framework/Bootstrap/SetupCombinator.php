<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;

/**
 * @psalm-suppress MixedArgumentTypeCoercion
 */
final class SetupCombinator
{
    public function __construct(
        private SetupGacela $original,
    ) {
    }

    public function combine(SetupGacela $other): SetupGacela
    {
        $this->overrideResetInMemoryCache($other);
        $this->overrideFileCacheSettings($other);

        $this->combineExternalServices($other);
        $this->combineProjectNamespaces($other);
        $this->combineConfigKeyValues($other);
        $this->combineEventDispatcher($other);
        $this->combineServicesToExtend($other);
        $this->combinePlugins($other);
        $this->combineGacelaConfigsToExtend($other);

        return $this->original;
    }

    private function overrideResetInMemoryCache(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::shouldResetInMemoryCache)) {
            $this->original->setShouldResetInMemoryCache($other->shouldResetInMemoryCache());
        }
    }

    private function overrideFileCacheSettings(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::fileCacheEnabled)) {
            $this->original->setFileCacheEnabled($other->isFileCacheEnabled());
        }
        if ($other->isPropertyChanged(SetupGacela::fileCacheDirectory)) {
            $this->original->setFileCacheDirectory($other->getFileCacheDirectory());
        }
    }

    private function combineExternalServices(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::externalServices)) {
            $this->original->combineExternalServices($other->externalServices());
        }
    }

    private function combineProjectNamespaces(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::projectNamespaces)) {
            $this->original->combineProjectNamespaces($other->getProjectNamespaces());
        }
    }

    private function combineConfigKeyValues(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::configKeyValues)) {
            $this->original->combineConfigKeyValues($other->getConfigKeyValues());
        }
    }

    private function combineEventDispatcher(SetupGacela $other): void
    {
        if ($other->canCreateEventDispatcher()) {
            if ($this->original->getEventDispatcher() instanceof ConfigurableEventDispatcher) {
                $eventDispatcher = $this->original->getEventDispatcher();
            } else {
                $eventDispatcher = new ConfigurableEventDispatcher();
            }
            /** @var ConfigurableEventDispatcher $eventDispatcher */
            $eventDispatcher->registerGenericListeners((array)$other->getGenericListeners());

            foreach ($other->getSpecificListeners() ?? [] as $event => $listeners) {
                foreach ($listeners as $callable) {
                    $eventDispatcher->registerSpecificListener($event, $callable);
                }
            }
        } else {
            $eventDispatcher = new NullEventDispatcher();
        }
        $this->original->setEventDispatcher($eventDispatcher);
    }

    private function combineServicesToExtend(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::servicesToExtend)) {
            foreach ($other->getServicesToExtend() as $serviceId => $otherServiceToExtend) {
                $this->original->addServicesToExtend($serviceId, $otherServiceToExtend);
            }
        }
    }

    private function combinePlugins(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::plugins)) {
            $this->original->combinePlugins($other->getPlugins());
        }
    }

    private function combineGacelaConfigsToExtend(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::gacelaConfigsToExtend)) {
            $this->original->combineGacelaConfigsToExtend($other->getGacelaConfigsToExtend());
        }
    }
}
