<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Closure;
use Gacela\Framework\Bootstrap\BuilderConfigurationInterface;
use Gacela\Framework\Bootstrap\ContainerConfigurationInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;

/**
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 * @psalm-import-type ExternalServicesMap from BuilderConfigurationInterface
 * @psalm-import-type ServicesToExtendMap from ContainerConfigurationInterface
 * @psalm-import-type HandlerRegistriesMap from ContainerConfigurationInterface
 * @psalm-import-type SpecificListenersMap from ConfigurableEventDispatcher
 */
final class GacelaConfigTransfer
{
    /**
     * @param ?ExternalServicesMap $externalServices
     * @param ?list<string> $projectNamespaces
     * @param ?list<string> $appModulePaths
     * @param ?array<string,mixed> $configKeyValues
     * @param ?list<callable> $genericListeners
     * @param ?SpecificListenersMap $specificListeners
     * @param ?list<class-string> $gacelaConfigsToExtend
     * @param ?list<class-string|callable> $plugins
     * @param ?ServicesToExtendMap $servicesToExtend
     * @param array<string,Closure> $factories
     * @param array<string,Closure> $protectedServices
     * @param array<string,string> $aliases
     * @param array<string,BindingsMap> $contextualBindings
     * @param HandlerRegistriesMap $handlerRegistries
     * @param array<string,Closure> $lazyServices
     */
    public function __construct(
        public readonly AppConfigBuilder $appConfigBuilder,
        public readonly SuffixTypesBuilder $suffixTypesBuilder,
        public readonly BindingsBuilder $bindingsBuilder,
        public readonly ?array $externalServices,
        public readonly ?bool $shouldResetInMemoryCache,
        public readonly ?bool $fileCacheEnabled,
        public readonly ?string $fileCacheDirectory,
        public readonly ?array $projectNamespaces,
        public readonly ?array $appModulePaths,
        public readonly ?array $configKeyValues,
        public readonly ?array $genericListeners,
        public readonly ?array $specificListeners,
        public readonly ?bool $areEventListenersEnabled,
        public readonly ?array $gacelaConfigsToExtend,
        public readonly ?array $plugins,
        public readonly ?array $servicesToExtend,
        public readonly array $factories,
        public readonly array $protectedServices,
        public readonly array $aliases,
        public readonly array $contextualBindings,
        public readonly array $handlerRegistries = [],
        public readonly array $lazyServices = [],
    ) {
    }
}
