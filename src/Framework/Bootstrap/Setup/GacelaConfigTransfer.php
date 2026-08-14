<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\BuilderConfigurationInterface;

use Gacela\Framework\Bootstrap\ContainerConfigurationInterface;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Config\Schema\ConfigType;
use Gacela\Framework\Dto\Schema\DtoType;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;

/**
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 * @psalm-import-type ExternalServicesMap from BuilderConfigurationInterface
 * @psalm-import-type ServiceFactoryMap from ContainerConfigurationInterface
 * @psalm-import-type ServiceAliasMap from ContainerConfigurationInterface
 * @psalm-import-type ServicesToExtendMap from ContainerConfigurationInterface
 * @psalm-import-type HandlerRegistriesMap from ContainerConfigurationInterface
 * @psalm-import-type PluginStacksMap from ContainerConfigurationInterface
 * @psalm-import-type ProviderServicesToExtendMap from ContainerConfigurationInterface
 * @psalm-import-type TagsMap from ContainerConfigurationInterface
 * @psalm-import-type AfterResolvingMap from ContainerConfigurationInterface
 * @psalm-import-type DefinitionSources from ContainerConfigurationInterface
 * @psalm-import-type ContextualBindingsMap from ContainerConfigurationInterface
 * @psalm-import-type ConfigKeyValues from SetupGacelaInterface
 * @psalm-import-type SpecificListenersMap from ConfigurableEventDispatcher
 */
final class GacelaConfigTransfer
{
    /**
     * @param ?ExternalServicesMap $externalServices
     * @param ?list<string> $projectNamespaces
     * @param ?list<string> $appModulePaths
     * @param ?ConfigKeyValues $configKeyValues
     * @param ?list<callable> $genericListeners
     * @param ?SpecificListenersMap $specificListeners
     * @param ?list<class-string> $gacelaConfigsToExtend
     * @param ?list<class-string|callable> $plugins
     * @param ?ServicesToExtendMap $servicesToExtend
     * @param ProviderServicesToExtendMap $providerServicesToExtend
     * @param ServiceFactoryMap $factories
     * @param ServiceFactoryMap $protectedServices
     * @param ServiceAliasMap $aliases
     * @param ContextualBindingsMap $contextualBindings
     * @param HandlerRegistriesMap $handlerRegistries
     * @param PluginStacksMap $pluginStacks
     * @param ServiceFactoryMap $lazyServices
     * @param TagsMap $tags
     * @param AfterResolvingMap $afterResolvingCallbacks
     * @param DefinitionSources $definitions
     * @param ?array<string, ConfigType> $configSchema
     * @param ?array<string, array<string, DtoType>> $dtoSchema
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
        /** @var ?list<string> */
        public readonly ?array $configDimensions,
        public readonly ?array $appModulePaths,
        public readonly ?array $configKeyValues,
        public readonly ?array $genericListeners,
        public readonly ?array $specificListeners,
        public readonly ?bool $areEventListenersEnabled,
        public readonly ?array $gacelaConfigsToExtend,
        public readonly ?array $plugins,
        public readonly ?array $servicesToExtend,
        public readonly array $providerServicesToExtend = [],
        public readonly array $factories = [],
        public readonly array $protectedServices = [],
        public readonly array $aliases = [],
        public readonly array $contextualBindings = [],
        public readonly array $handlerRegistries = [],
        public readonly array $pluginStacks = [],
        public readonly array $lazyServices = [],
        public readonly array $tags = [],
        public readonly array $afterResolvingCallbacks = [],
        public readonly array $definitions = [],
        public readonly ?array $configSchema = null,
        public readonly ?bool $shouldValidateConfigSchemaOnBoot = null,
        public readonly ?string $stubsDir = null,
        public readonly ?array $dtoSchema = null,
        public readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }
}
