<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Closure;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;

/**
 * Merges two SetupGacela instances together with conditional logic based on change tracking.
 *
 * @psalm-suppress MixedArgumentTypeCoercion
 */
final class SetupMerger
{
    public function __construct(
        private readonly SetupGacela $original,
    ) {
    }

    public function merge(SetupGacela $other): SetupGacela
    {
        $this->whenChanged($other, SetupGacela::shouldResetInMemoryCache, fn () => $this->original->setShouldResetInMemoryCache($other->shouldResetInMemoryCache()));
        $this->whenChanged($other, SetupGacela::fileCacheEnabled, fn () => $this->original->setFileCacheEnabled($other->isFileCacheEnabled()));
        $this->whenChanged($other, SetupGacela::fileCacheDirectory, fn () => $this->original->setFileCacheDirectory($other->getFileCacheDirectory()));

        $this->whenChanged($other, SetupGacela::externalServices, fn () => $this->original->mergeExternalServices($other->externalServices()));
        $this->whenChanged($other, SetupGacela::projectNamespaces, fn () => $this->original->mergeProjectNamespaces($other->getProjectNamespaces()));
        $this->whenChanged($other, SetupGacela::configKeyValues, fn () => $this->original->mergeConfigKeyValues($other->getConfigKeyValues()));
        $this->mergeEventDispatcher($other);
        $this->mergeServicesToExtend($other);
        $this->whenChanged($other, SetupGacela::factories, fn () => $this->original->mergeFactories($other->getFactories()));
        $this->whenChanged($other, SetupGacela::protectedServices, fn () => $this->original->mergeProtectedServices($other->getProtectedServices()));
        $this->whenChanged($other, SetupGacela::aliases, fn () => $this->original->mergeAliases($other->getAliases()));
        $this->whenChanged($other, SetupGacela::contextualBindings, fn () => $this->original->mergeContextualBindings($other->getContextualBindings()));
        $this->whenChanged($other, SetupGacela::handlerRegistries, fn () => $this->original->mergeHandlerRegistries($other->getHandlerRegistries()));
        $this->whenChanged($other, SetupGacela::tags, fn () => $this->original->mergeTags($other->getTags()));
        $this->whenChanged($other, SetupGacela::afterResolvingCallbacks, fn () => $this->original->mergeAfterResolvingCallbacks($other->getAfterResolvingCallbacks()));
        $this->whenChanged($other, SetupGacela::lazyServices, fn () => $this->original->mergeLazyServices($other->getLazyServices()));
        $this->whenChanged($other, SetupGacela::plugins, fn () => $this->original->mergePlugins($other->getPlugins()));
        $this->whenChanged($other, SetupGacela::gacelaConfigsToExtend, fn () => $this->original->mergeGacelaConfigsToExtend($other->getGacelaConfigsToExtend()));

        return $this->original;
    }

    /**
     * Apply $merge only when $other explicitly set $property, so an untouched
     * property on the incoming setup never overwrites the original's value.
     *
     * @param Closure():mixed $merge
     */
    private function whenChanged(SetupGacela $other, string $property, Closure $merge): void
    {
        if ($other->isPropertyChanged($property)) {
            $merge();
        }
    }

    private function mergeEventDispatcher(SetupGacela $other): void
    {
        if ($other->canCreateEventDispatcher()) {
            if ($this->original->getEventDispatcher() instanceof ConfigurableEventDispatcher) {
                $eventDispatcher = $this->original->getEventDispatcher();
            } else {
                $eventDispatcher = new ConfigurableEventDispatcher();
            }

            /** @var ConfigurableEventDispatcher $eventDispatcher */
            $eventDispatcher->registerGenericListeners($other->getGenericListeners() ?? []);

            foreach ($other->getSpecificListeners() ?? [] as $event => $listeners) {
                foreach ($listeners as $callable) {
                    $eventDispatcher->registerSpecificListener($event, $callable);
                }
            }
        } else {
            $eventDispatcher = $this->original->getEventDispatcher();
        }

        $this->original->setEventDispatcher($eventDispatcher);
    }

    private function mergeServicesToExtend(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::servicesToExtend)) {
            foreach ($other->getServicesToExtend() as $serviceId => $otherServiceToExtend) {
                $this->original->addServicesToExtend($serviceId, $otherServiceToExtend);
            }
        }
    }
}
