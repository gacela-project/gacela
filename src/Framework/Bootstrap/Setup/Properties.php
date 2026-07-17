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
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;

/**
 * Value object that holds all SetupGacela properties.
 *
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 * @psalm-import-type ExternalServicesMap from BuilderConfigurationInterface
 * @psalm-import-type ServicesToExtendMap from ContainerConfigurationInterface
 * @psalm-import-type HandlerRegistriesMap from ContainerConfigurationInterface
 * @psalm-import-type SpecificListenersMap from ConfigurableEventDispatcher
 */
final class Properties
{
    /** @var callable(AppConfigBuilder):void */
    public $appConfigFn;

    /** @var callable(BindingsBuilder,array<string,mixed>):void */
    public $bindingsFn;

    /** @var callable(SuffixTypesBuilder):void */
    public $suffixTypesFn;

    /** @var ?ExternalServicesMap */
    public ?array $externalServices = null;

    public ?AppConfigBuilder $appConfigBuilder = null;

    public ?SuffixTypesBuilder $suffixTypesBuilder = null;

    public ?BindingsBuilder $bindingsBuilder = null;

    public ?bool $shouldResetInMemoryCache = null;

    public ?bool $fileCacheEnabled = null;

    public ?string $fileCacheDirectory = null;

    /** @var ?list<string> */
    public ?array $projectNamespaces = null;

    /** @var ?list<string> */
    public ?array $appModulePaths = null;

    /** @var ?array<string,mixed> */
    public ?array $configKeyValues = null;

    public ?bool $areEventListenersEnabled = null;

    /** @var ?list<callable> */
    public ?array $genericListeners = null;

    /** @var ?SpecificListenersMap */
    public ?array $specificListeners = null;

    public ?EventDispatcherInterface $eventDispatcher = null;

    /** @var ?ServicesToExtendMap */
    public ?array $servicesToExtend = null;

    /** @var ?array<string,Closure> */
    public ?array $factories = null;

    /** @var ?array<string,Closure> */
    public ?array $protectedServices = null;

    /** @var ?array<string,string> */
    public ?array $aliases = null;

    /** @var ?array<string,BindingsMap> */
    public ?array $contextualBindings = null;

    /** @var ?array<string,Closure> */
    public ?array $lazyServices = null;

    /** @var ?list<class-string> */
    public ?array $gacelaConfigsToExtend = null;

    /** @var ?list<class-string|callable> */
    public ?array $plugins = null;

    /** @var ?HandlerRegistriesMap */
    public ?array $handlerRegistries = null;

    public function __construct()
    {
        $emptyFn = static function (): void {};

        $this->appConfigFn = $emptyFn;
        $this->bindingsFn = $emptyFn;
        $this->suffixTypesFn = $emptyFn;
    }
}
