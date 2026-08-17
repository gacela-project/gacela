<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Closure;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;

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
        $this->whenChanged($other, SetupGacela::shouldResetInMemoryCache, fn (): \Gacela\Framework\Bootstrap\SetupGacela => $this->original->setShouldResetInMemoryCache($other->shouldResetInMemoryCache()));
        $this->whenChanged($other, SetupGacela::fileCacheEnabled, fn (): \Gacela\Framework\Bootstrap\SetupGacela => $this->original->setFileCacheEnabled($other->isFileCacheEnabled()));
        $this->whenChanged($other, SetupGacela::fileCacheDirectory, fn (): \Gacela\Framework\Bootstrap\SetupGacela => $this->original->setFileCacheDirectory($other->getFileCacheDirectory()));

        $this->whenChanged($other, SetupGacela::externalServices, fn () => $this->original->mergeExternalServices($other->externalServices()));
        $this->whenChanged($other, SetupGacela::projectNamespaces, fn () => $this->original->mergeProjectNamespaces($other->getProjectNamespaces()));
        // Replaced rather than merged, unlike the namespaces above: this is the
        // list of directories to scan, and a source that names it is naming all
        // of them. Missing entirely until now, so `setAppModulePaths()` written
        // in `gacela.php` -- where a project naturally writes it, beside
        // `setProjectNamespaces()` -- was silently ignored and every command
        // walked the whole application root.
        $this->whenChanged($other, SetupGacela::appModulePaths, fn (): SetupGacela => $this->original->setAppModulePaths($other->getAppModulePaths()));
        $this->whenChanged($other, SetupGacela::configDimensions, fn () => $this->original->mergeConfigDimensions($other->getConfigDimensions()));
        $this->whenChanged($other, SetupGacela::configKeyValues, fn () => $this->original->mergeConfigKeyValues($other->getConfigKeyValues()));
        $this->whenChanged($other, SetupGacela::configSchema, fn () => $this->original->mergeConfigSchema($other->getConfigSchema()));
        $this->whenChanged($other, SetupGacela::dtoSchema, fn () => $this->original->mergeDtoSchema($other->getDtoSchema()));
        $this->whenChanged($other, SetupGacela::stubsDir, fn (): SetupGacela => $this->original->setStubsDir($other->getStubsDir()));
        $this->mergeEventDispatcher($other);
        $this->mergeServicesToExtend($other);
        $this->whenChanged($other, SetupGacela::factories, fn () => $this->original->mergeFactories($other->getFactories()));
        $this->whenChanged($other, SetupGacela::protectedServices, fn () => $this->original->mergeProtectedServices($other->getProtectedServices()));
        $this->whenChanged($other, SetupGacela::aliases, fn () => $this->original->mergeAliases($other->getAliases()));
        $this->whenChanged($other, SetupGacela::contextualBindings, fn () => $this->original->mergeContextualBindings($other->getContextualBindings()));
        $this->whenChanged($other, SetupGacela::handlerRegistries, fn () => $this->original->mergeHandlerRegistries($other->getHandlerRegistries()));
        $this->whenChanged($other, SetupGacela::pluginStacks, fn () => $this->original->mergePluginStacks($other->getPluginStacks()));
        $this->whenChanged($other, SetupGacela::providerServicesToExtend, fn () => $this->original->mergeProviderServicesToExtend($other->getProviderServicesToExtend()));
        $this->whenChanged($other, SetupGacela::tags, fn () => $this->original->mergeTags($other->getTags()));
        $this->whenChanged($other, SetupGacela::afterResolvingCallbacks, fn () => $this->original->mergeAfterResolvingCallbacks($other->getAfterResolvingCallbacks()));
        $this->whenChanged($other, SetupGacela::lazyServices, fn () => $this->original->mergeLazyServices($other->getLazyServices()));
        $this->whenChanged($other, SetupGacela::definitions, fn () => $this->original->mergeDefinitions($other->getDefinitions()));
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

    /**
     * The listeners are carried onto the merged setup the way every other
     * property here is, and the dispatcher is *derived* from it afterwards --
     * one source of truth, and the one `doctor` and `debug:events` read.
     *
     * They used to be registered straight onto the dispatcher instead. The
     * listener ran and nothing recorded it, so both commands answered "nothing
     * is listening" about a listener that was firing (#887). Registering as we
     * go *and* recording is the trap: the dispatcher is rebuilt from the setup,
     * so anything registered by hand as well arrives twice.
     *
     * The handover is carried separately from the listeners, because it answers
     * a different question -- what delivers the events, rather than what runs.
     * A setup that brought listeners used to have a fresh
     * `ConfigurableEventDispatcher` installed to hold them, and since that class
     * is `final` an application's own dispatcher could never be the one kept:
     * `setEventDispatcher()` plus one listener in `gacela.php` dropped the
     * application's bus (#888).
     */
    private function mergeEventDispatcher(SetupGacela $other): void
    {
        if ($other->canCreateEventDispatcher()) {
            $this->original->mergeGenericListeners($other->getGenericListeners() ?? []);
            $this->original->mergeSpecificListeners($other->getSpecificListeners() ?? []);
        }

        $suppliedByOther = $other->getSuppliedEventDispatcher();

        if ($suppliedByOther instanceof EventDispatcherInterface) {
            $this->original->setEventDispatcher($suppliedByOther);
        }
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
