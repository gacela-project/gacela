<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;
use Gacela\Framework\Bootstrap\Setup\BuilderExecutor;
use Gacela\Framework\Bootstrap\Setup\GacelaConfigExtender;
use Gacela\Framework\Bootstrap\Setup\Properties;
use Gacela\Framework\Bootstrap\Setup\PropertyChangeTracker;
use Gacela\Framework\Bootstrap\Setup\PropertyMerger;
use Gacela\Framework\Bootstrap\Setup\SetupInitializer;
use Gacela\Framework\Bootstrap\Setup\SetupMerger;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use RuntimeException;

use function is_callable;
use function sprintf;

/**
 * @psalm-suppress ArgumentTypeCoercion,MixedArgumentTypeCoercion
 */
final class SetupGacela extends AbstractSetupGacela
{
    private readonly Properties $properties;

    private readonly PropertyChangeTracker $changeTracker;

    private readonly BuilderExecutor $builderExecutor;

    private readonly PropertyMerger $propertyMerger;

    public function __construct()
    {
        $this->properties = new Properties();
        $this->changeTracker = new PropertyChangeTracker();
        $this->builderExecutor = new BuilderExecutor($this->properties);
        $this->propertyMerger = new PropertyMerger($this);
    }

    /**
     * @codeCoverageIgnore
     */
    public static function fromFile(string $gacelaFilePath): self
    {
        if (!is_file($gacelaFilePath)) {
            throw new RuntimeException(sprintf("Invalid file path: '%s'", $gacelaFilePath));
        }

        /** @var callable(GacelaConfig):void|null $setupGacelaFileFn */
        $setupGacelaFileFn = include $gacelaFilePath;
        if (!is_callable($setupGacelaFileFn)) {
            return new self();
        }

        return self::fromCallable($setupGacelaFileFn);
    }

    /**
     * @param callable(GacelaConfig):void $setupGacelaFileFn
     */
    public static function fromCallable(callable $setupGacelaFileFn): self
    {
        $gacelaConfig = new GacelaConfig();
        $setupGacelaFileFn($gacelaConfig);

        return self::fromGacelaConfig($gacelaConfig);
    }

    public static function fromGacelaConfig(GacelaConfig $gacelaConfig): self
    {
        (new GacelaConfigExtender())->extend($gacelaConfig);

        $dto = $gacelaConfig->toTransfer();
        $setup = new self();

        return (new SetupInitializer($setup))->initializeFromTransfer($dto);
    }

    /**
     * @param array<string,class-string|object|callable> $array
     */
    public function setExternalServices(?array $array): self
    {
        $this->markPropertyAsChanged(self::externalServices, $array !== null);
        $this->properties->externalServices = $array; // No default fallback for external services

        return $this;
    }

    public function setAppConfigBuilder(AppConfigBuilder $builder): self
    {
        $this->properties->appConfigBuilder = $builder;

        return $this;
    }

    public function setSuffixTypesBuilder(SuffixTypesBuilder $builder): self
    {
        $this->properties->suffixTypesBuilder = $builder;

        return $this;
    }

    public function setBindingsBuilder(BindingsBuilder $builder): self
    {
        $this->properties->bindingsBuilder = $builder;

        return $this;
    }

    /**
     * @param callable(AppConfigBuilder):void $callable
     */
    public function setAppConfigFn(callable $callable): self
    {
        $this->properties->appConfigFn = $callable;

        return $this;
    }

    public function buildAppConfig(AppConfigBuilder $builder): AppConfigBuilder
    {
        $builder = parent::buildAppConfig($builder);

        return $this->builderExecutor->buildAppConfig($builder);
    }

    /**
     * @param callable(BindingsBuilder,array<string,mixed>):void $callable
     */
    public function setBindingsFn(callable $callable): self
    {
        $this->properties->bindingsFn = $callable;

        return $this;
    }

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param array<string,class-string|object|callable> $externalServices
     */
    public function buildBindings(
        BindingsBuilder $builder,
        array $externalServices,
    ): BindingsBuilder {
        $builder = parent::buildBindings($builder, $externalServices);

        return $this->builderExecutor->buildBindings($builder, $externalServices);
    }

    /**
     * @param callable(SuffixTypesBuilder):void $callable
     */
    public function setSuffixTypesFn(callable $callable): self
    {
        $this->properties->suffixTypesFn = $callable;

        return $this;
    }

    /**
     * Allow overriding gacela resolvable types.
     */
    public function buildSuffixTypes(SuffixTypesBuilder $builder): SuffixTypesBuilder
    {
        $builder = parent::buildSuffixTypes($builder);

        return $this->builderExecutor->buildSuffixTypes($builder);
    }

    /**
     * @return array<string, class-string|object|callable>
     */
    public function externalServices(): array
    {
        return array_merge(
            parent::externalServices(),
            $this->properties->externalServices ?? [],
        );
    }

    public function setShouldResetInMemoryCache(?bool $flag): self
    {
        $this->properties->shouldResetInMemoryCache = $this->setPropertyWithTracking(
            self::shouldResetInMemoryCache,
            $flag,
            self::DEFAULT_SHOULD_RESET_IN_MEMORY_CACHE,
        );

        return $this;
    }

    public function shouldResetInMemoryCache(): bool
    {
        return $this->properties->shouldResetInMemoryCache ?? self::DEFAULT_SHOULD_RESET_IN_MEMORY_CACHE;
    }

    public function isFileCacheEnabled(): bool
    {
        return $this->properties->fileCacheEnabled ?? self::DEFAULT_FILE_CACHE_ENABLED;
    }

    public function getFileCacheDirectory(): string
    {
        return $this->properties->fileCacheDirectory ?? '';
    }

    public function setFileCacheDirectory(?string $dir): self
    {
        $this->properties->fileCacheDirectory = $this->setPropertyWithTracking(
            self::fileCacheDirectory,
            $dir,
            self::DEFAULT_FILE_CACHE_DIRECTORY,
        );

        return $this;
    }

    /**
     * @param ?list<string> $list
     */
    public function setProjectNamespaces(?array $list): self
    {
        $this->properties->projectNamespaces = $this->setPropertyWithTracking(
            self::projectNamespaces,
            $list,
            self::DEFAULT_PROJECT_NAMESPACES,
        );

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getProjectNamespaces(): array
    {
        return $this->properties->projectNamespaces ?? self::DEFAULT_PROJECT_NAMESPACES;
    }

    /**
     * @return array<string,mixed>
     */
    public function getConfigKeyValues(): array
    {
        return $this->properties->configKeyValues ?? self::DEFAULT_CONFIG_KEY_VALUES;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->properties->eventDispatcher ??= SetupEventDispatcher::getDispatcher($this);
    }

    /**
     * @return array<string,list<Closure>>
     */
    public function getServicesToExtend(): array
    {
        return $this->properties->servicesToExtend ?? self::DEFAULT_SERVICES_TO_EXTEND;
    }

    /**
     * @return array<string,Closure>
     */
    public function getFactories(): array
    {
        return $this->properties->factories ?? self::DEFAULT_FACTORIES;
    }

    /**
     * @return array<string,Closure>
     */
    public function getProtectedServices(): array
    {
        return $this->properties->protectedServices ?? self::DEFAULT_PROTECTED_SERVICES;
    }

    /**
     * @return array<string,string>
     */
    public function getAliases(): array
    {
        return $this->properties->aliases ?? self::DEFAULT_ALIASES;
    }

    public function setFileCacheEnabled(?bool $flag): self
    {
        $this->properties->fileCacheEnabled = $this->setPropertyWithTracking(
            self::fileCacheEnabled,
            $flag,
            self::DEFAULT_FILE_CACHE_ENABLED,
        );

        return $this;
    }

    public function canCreateEventDispatcher(): bool
    {
        return $this->properties->areEventListenersEnabled === true
            && $this->hasEventListeners();
    }

    /**
     * @param ?array<string,mixed> $configKeyValues
     */
    public function setConfigKeyValues(?array $configKeyValues): self
    {
        $this->properties->configKeyValues = $this->setPropertyWithTracking(
            self::configKeyValues,
            $configKeyValues,
            self::DEFAULT_CONFIG_KEY_VALUES,
        );

        return $this;
    }

    /**
     * @return array<class-string,list<callable>>|null
     */
    public function getSpecificListeners(): ?array
    {
        return $this->properties->specificListeners;
    }

    /**
     * @return list<callable>|null
     */
    public function getGenericListeners(): ?array
    {
        return $this->properties->genericListeners;
    }

    public function isPropertyChanged(string $name): bool
    {
        return $this->changeTracker->isChanged($name);
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->properties->eventDispatcher = $eventDispatcher;

        return $this;
    }

    public function merge(self $other): self
    {
        return (new SetupMerger($this))->merge($other);
    }

    /**
     * @param list<Closure> $servicesToExtend
     */
    public function addServicesToExtend(string $serviceId, array $servicesToExtend): self
    {
        $this->properties->servicesToExtend[$serviceId] ??= [];
        $this->properties->servicesToExtend[$serviceId] = [...$this->properties->servicesToExtend[$serviceId], ...$servicesToExtend];

        return $this;
    }

    /**
     * @param array<string,class-string|object|callable> $list
     */
    public function mergeExternalServices(array $list): void
    {
        $this->propertyMerger->mergeExternalServices($list);
    }

    /**
     * @param list<string> $list
     */
    public function mergeProjectNamespaces(array $list): void
    {
        $this->propertyMerger->mergeProjectNamespaces($list);
    }

    /**
     * @param array<string,mixed> $list
     */
    public function mergeConfigKeyValues(array $list): void
    {
        $this->propertyMerger->mergeConfigKeyValues($list);
    }

    /**
     * @param list<class-string> $list
     */
    public function mergeGacelaConfigsToExtend(array $list): void
    {
        $this->propertyMerger->mergeGacelaConfigsToExtend($list);
    }

    /**
     * @param list<class-string|callable> $list
     */
    public function mergePlugins(array $list): void
    {
        $this->propertyMerger->mergePlugins($list);
    }

    /**
     * @return list<class-string>
     */
    public function getGacelaConfigsToExtend(): array
    {
        return $this->properties->gacelaConfigsToExtend ?? self::DEFAULT_GACELA_CONFIGS_TO_EXTEND;
    }

    /**
     * @return list<class-string|callable>
     */
    public function getPlugins(): array
    {
        return $this->properties->plugins ?? self::DEFAULT_PLUGINS;
    }

    /**
     * @internal Used by PropertyMerger - do not call directly
     *
     * @param ?list<class-string> $list
     */
    public function setGacelaConfigsToExtend(?array $list): self
    {
        $this->properties->gacelaConfigsToExtend = $this->setPropertyWithTracking(
            self::gacelaConfigsToExtend,
            $list,
            self::DEFAULT_GACELA_CONFIGS_TO_EXTEND,
        );

        return $this;
    }

    /**
     * @internal Used by PropertyMerger - do not call directly
     *
     * @param ?list<class-string|callable> $list
     */
    public function setPlugins(?array $list): self
    {
        $this->properties->plugins = $this->setPropertyWithTracking(
            self::plugins,
            $list,
            self::DEFAULT_PLUGINS,
        );

        return $this;
    }

    /**
     * @internal Used by SetupInitializer - do not call directly
     */
    public function setAreEventListenersEnabled(?bool $flag): self
    {
        $this->properties->areEventListenersEnabled = $flag ?? self::DEFAULT_ARE_EVENT_LISTENERS_ENABLED;

        return $this;
    }

    /**
     * @internal Used by SetupInitializer - do not call directly
     *
     * @param ?list<callable> $listeners
     */
    public function setGenericListeners(?array $listeners): self
    {
        $this->properties->genericListeners = $listeners ?? self::DEFAULT_GENERIC_LISTENERS;

        return $this;
    }

    /**
     * @internal Used by SetupInitializer - do not call directly
     *
     * @param ?array<string,list<Closure>> $list
     */
    public function setServicesToExtend(?array $list): self
    {
        $this->properties->servicesToExtend = $this->setPropertyWithTracking(
            self::servicesToExtend,
            $list,
            self::DEFAULT_SERVICES_TO_EXTEND,
        );

        return $this;
    }

    /**
     * @internal Used by SetupInitializer - do not call directly
     *
     * @param ?array<string,Closure> $list
     */
    public function setFactories(?array $list): self
    {
        $this->properties->factories = $this->setPropertyWithTracking(
            self::factories,
            $list,
            self::DEFAULT_FACTORIES,
        );

        return $this;
    }

    /**
     * @internal Used by SetupInitializer - do not call directly
     *
     * @param ?array<string,Closure> $list
     */
    public function setProtectedServices(?array $list): self
    {
        $this->properties->protectedServices = $this->setPropertyWithTracking(
            self::protectedServices,
            $list,
            self::DEFAULT_PROTECTED_SERVICES,
        );

        return $this;
    }

    /**
     * @internal Used by SetupInitializer - do not call directly
     *
     * @param ?array<string,string> $list
     */
    public function setAliases(?array $list): self
    {
        $this->properties->aliases = $this->setPropertyWithTracking(
            self::aliases,
            $list,
            self::DEFAULT_ALIASES,
        );

        return $this;
    }

    /**
     * @internal Used by SetupInitializer - do not call directly
     *
     * @param ?array<class-string,list<callable>> $listeners
     */
    public function setSpecificListeners(?array $listeners): self
    {
        $this->properties->specificListeners = $listeners ?? self::DEFAULT_SPECIFIC_LISTENERS;

        return $this;
    }

    private function hasEventListeners(): bool
    {
        return ($this->properties->genericListeners !== null && $this->properties->genericListeners !== [])
            || ($this->properties->specificListeners !== null && $this->properties->specificListeners !== []);
    }

    /**
     * Helper method to set a property with change tracking and default value.
     *
     * @template T
     *
     * @param T $value
     * @param T $default
     *
     * @return T
     */
    private function setPropertyWithTracking(string $propertyName, mixed $value, mixed $default): mixed
    {
        $this->markPropertyAsChanged($propertyName, $value !== null);
        return $value ?? $default;
    }

    private function markPropertyAsChanged(string $name, bool $isChanged): void
    {
        if ($isChanged) {
            $this->changeTracker->markAsChanged($name);
        } else {
            $this->changeTracker->markAsUnchanged($name);
        }
    }
}
