<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\SetupGacela;

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
        $this->overrideResetInMemoryCache($other);
        $this->overrideFileCacheSettings($other);

        $this->mergeExternalServices($other);
        $this->mergeProjectNamespaces($other);
        $this->mergeConfigKeyValues($other);
        $this->mergeServicesToExtend($other);
        $this->mergeFactories($other);
        $this->mergeProtectedServices($other);
        $this->mergeAliases($other);
        $this->mergeContextualBindings($other);
        $this->mergeLazyServices($other);
        $this->mergePlugins($other);
        $this->mergeGacelaConfigsToExtend($other);

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

    private function mergeExternalServices(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::externalServices)) {
            $this->original->mergeExternalServices($other->externalServices());
        }
    }

    private function mergeProjectNamespaces(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::projectNamespaces)) {
            $this->original->mergeProjectNamespaces($other->getProjectNamespaces());
        }
    }

    private function mergeConfigKeyValues(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::configKeyValues)) {
            $this->original->mergeConfigKeyValues($other->getConfigKeyValues());
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

    private function mergePlugins(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::plugins)) {
            $this->original->mergePlugins($other->getPlugins());
        }
    }

    private function mergeGacelaConfigsToExtend(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::gacelaConfigsToExtend)) {
            $this->original->mergeGacelaConfigsToExtend($other->getGacelaConfigsToExtend());
        }
    }

    private function mergeFactories(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::factories)) {
            $this->original->mergeFactories($other->getFactories());
        }
    }

    private function mergeProtectedServices(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::protectedServices)) {
            $this->original->mergeProtectedServices($other->getProtectedServices());
        }
    }

    private function mergeAliases(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::aliases)) {
            $this->original->mergeAliases($other->getAliases());
        }
    }

    private function mergeContextualBindings(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::contextualBindings)) {
            $this->original->mergeContextualBindings($other->getContextualBindings());
        }
    }

    private function mergeLazyServices(SetupGacela $other): void
    {
        if ($other->isPropertyChanged(SetupGacela::lazyServices)) {
            $this->original->mergeLazyServices($other->getLazyServices());
        }
    }
}
