<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Closure;
use Gacela\Framework\Bootstrap\BuilderConfigurationInterface;
use Gacela\Framework\Bootstrap\ContainerConfigurationInterface;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

use function array_merge;
use function array_unique;

/**
 * Merges individual properties into a SetupGacela instance.
 *
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 * @psalm-import-type ExternalServicesMap from BuilderConfigurationInterface
 * @psalm-import-type HandlerRegistriesMap from ContainerConfigurationInterface
 * @psalm-import-type ContextualBindingsMap from ContainerConfigurationInterface
 */
final class PropertyMerger
{
    public function __construct(
        private readonly SetupGacela $setup,
    ) {
    }

    /**
     * @param ExternalServicesMap $list
     */
    public function mergeExternalServices(array $list): void
    {
        $current = $this->setup->externalServices();
        $this->setup->setExternalServices(array_merge($current, $list));
    }

    /**
     * @param list<string> $list
     */
    public function mergeProjectNamespaces(array $list): void
    {
        $current = $this->setup->getProjectNamespaces();
        $this->setup->setProjectNamespaces(array_merge($current, $list));
    }

    /**
     * @param array<string,mixed> $list
     */
    public function mergeConfigKeyValues(array $list): void
    {
        $current = $this->setup->getConfigKeyValues();
        $this->setup->setConfigKeyValues(array_merge($current, $list));
    }

    /**
     * @param list<class-string> $list
     */
    public function mergeGacelaConfigsToExtend(array $list): void
    {
        $current = $this->setup->getGacelaConfigsToExtend();
        /** @var list<class-string> $merged */
        $merged = array_values(array_unique(array_merge($current, $list)));
        $this->setup->setGacelaConfigsToExtend($merged);
    }

    /**
     * @param list<class-string|callable> $list
     */
    public function mergePlugins(array $list): void
    {
        $current = $this->setup->getPlugins();
        $this->setup->setPlugins(array_merge($current, $list));
    }

    /**
     * @param array<string,Closure> $list
     */
    public function mergeFactories(array $list): void
    {
        $current = $this->setup->getFactories();
        $this->setup->setFactories(array_merge($current, $list));
    }

    /**
     * @param array<string,Closure> $list
     */
    public function mergeProtectedServices(array $list): void
    {
        $current = $this->setup->getProtectedServices();
        $this->setup->setProtectedServices(array_merge($current, $list));
    }

    /**
     * @param array<string,string> $list
     */
    public function mergeAliases(array $list): void
    {
        $current = $this->setup->getAliases();
        $this->setup->setAliases(array_merge($current, $list));
    }

    /**
     * @param ContextualBindingsMap $list
     */
    public function mergeContextualBindings(array $list): void
    {
        $this->setup->setContextualBindings(
            $this->mergeNested($this->setup->getContextualBindings(), $list),
        );
    }

    /**
     * @param HandlerRegistriesMap $list
     */
    public function mergeHandlerRegistries(array $list): void
    {
        $this->setup->setHandlerRegistries(
            $this->mergeNested($this->setup->getHandlerRegistries(), $list),
        );
    }

    /**
     * @param array<string,Closure> $list
     */
    public function mergeLazyServices(array $list): void
    {
        $current = $this->setup->getLazyServices();
        $this->setup->setLazyServices(array_merge($current, $list));
    }

    /**
     * Merge a two-level map, combining the inner maps key by key.
     *
     * @template TInner of array
     *
     * @param array<string,TInner> $current
     * @param array<string,TInner> $list
     *
     * @return array<string,TInner>
     */
    private function mergeNested(array $current, array $list): array
    {
        $merged = $current;

        foreach ($list as $key => $inner) {
            /** @var TInner $mergedInner */
            $mergedInner = array_merge($merged[$key] ?? [], $inner);
            $merged[$key] = $mergedInner;
        }

        return $merged;
    }
}
