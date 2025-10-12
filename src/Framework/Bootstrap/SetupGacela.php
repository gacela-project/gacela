<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;
use Gacela\Framework\Bootstrap\Setup\BuilderExecutor;
use Gacela\Framework\Bootstrap\Setup\GacelaConfigExtender;
use Gacela\Framework\Bootstrap\Setup\Properties;
use Gacela\Framework\Bootstrap\Setup\PropertyChangeTracker;
use Gacela\Framework\Bootstrap\Setup\PropertyMerger;
use Gacela\Framework\Bootstrap\Setup\SetupMerger;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Override;
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

        return (new self())
            ->setExternalServices($dto->externalServices)
            ->setAppConfigBuilder($dto->appConfigBuilder)
            ->setSuffixTypesBuilder($dto->suffixTypesBuilder)
            ->setBindingsBuilder($dto->bindingsBuilder)
            ->setShouldResetInMemoryCache($dto->shouldResetInMemoryCache)
            ->setFileCacheEnabled($dto->fileCacheEnabled)
            ->setFileCacheDirectory($dto->fileCacheDirectory)
            ->setProjectNamespaces($dto->projectNamespaces)
            ->setConfigKeyValues($dto->configKeyValues)
            ->setAreEventListenersEnabled($dto->areEventListenersEnabled)
            ->setGenericListeners($dto->genericListeners)
            ->setSpecificListeners($dto->specificListeners)
            ->setGacelaConfigsToExtend($dto->gacelaConfigsToExtend)
            ->setPlugins($dto->plugins)
            ->setServicesToExtend($dto->servicesToExtend);
    }

    /**
     * @param array<string,class-string|object|callable> $array
     */
    public function setExternalServices(?array $array): self
    {
        $this->markPropertyAsChanged(self::externalServices, $array !== null);
        $this->properties->externalServices = $array;

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

    #[Override]
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
    #[Override]
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
    #[Override]
    public function buildSuffixTypes(SuffixTypesBuilder $builder): SuffixTypesBuilder
    {
        $builder = parent::buildSuffixTypes($builder);

        return $this->builderExecutor->buildSuffixTypes($builder);
    }

    /**
     * @return array<string, class-string|object|callable>
     */
    #[Override]
    public function externalServices(): array
    {
        return array_merge(
            parent::externalServices(),
            $this->properties->externalServices ?? [],
        );
    }

    public function setShouldResetInMemoryCache(?bool $flag): self
    {
        $this->markPropertyAsChanged(self::shouldResetInMemoryCache, $flag !== null);
        $this->properties->shouldResetInMemoryCache = $flag ?? self::DEFAULT_SHOULD_RESET_IN_MEMORY_CACHE;

        return $this;
    }

    #[Override]
    public function shouldResetInMemoryCache(): bool
    {
        return $this->properties->shouldResetInMemoryCache ?? self::DEFAULT_SHOULD_RESET_IN_MEMORY_CACHE;
    }

    #[Override]
    public function isFileCacheEnabled(): bool
    {
        return $this->properties->fileCacheEnabled ?? self::DEFAULT_FILE_CACHE_ENABLED;
    }

    #[Override]
    public function getFileCacheDirectory(): string
    {
        return $this->properties->fileCacheDirectory ?? '';
    }

    public function setFileCacheDirectory(?string $dir): self
    {
        $this->markPropertyAsChanged(self::fileCacheDirectory, $dir !== null);
        $this->properties->fileCacheDirectory = $dir ?? self::DEFAULT_FILE_CACHE_DIRECTORY;

        return $this;
    }

    /**
     * @param ?list<string> $list
     */
    public function setProjectNamespaces(?array $list): self
    {
        $this->markPropertyAsChanged(self::projectNamespaces, $list !== null);
        $this->properties->projectNamespaces = $list ?? self::DEFAULT_PROJECT_NAMESPACES;

        return $this;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getProjectNamespaces(): array
    {
        return $this->properties->projectNamespaces ?? self::DEFAULT_PROJECT_NAMESPACES;
    }

    /**
     * @return array<string,mixed>
     */
    #[Override]
    public function getConfigKeyValues(): array
    {
        return $this->properties->configKeyValues ?? self::DEFAULT_CONFIG_KEY_VALUES;
    }

    #[Override]
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->properties->eventDispatcher ??= SetupEventDispatcher::getDispatcher($this);
    }

    /**
     * @return array<string,list<Closure>>
     */
    #[Override]
    public function getServicesToExtend(): array
    {
        return $this->properties->servicesToExtend ?? self::DEFAULT_SERVICES_TO_EXTEND;
    }

    public function setFileCacheEnabled(?bool $flag): self
    {
        $this->markPropertyAsChanged(self::fileCacheEnabled, $flag !== null);
        $this->properties->fileCacheEnabled = $flag ?? self::DEFAULT_FILE_CACHE_ENABLED;

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
        $this->markPropertyAsChanged(self::configKeyValues, $configKeyValues !== null);
        $this->properties->configKeyValues = $configKeyValues ?? self::DEFAULT_CONFIG_KEY_VALUES;

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

    #[Override]
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
    #[Override]
    public function getGacelaConfigsToExtend(): array
    {
        return $this->properties->gacelaConfigsToExtend ?? self::DEFAULT_GACELA_CONFIGS_TO_EXTEND;
    }

    /**
     * @return list<class-string|callable>
     */
    #[Override]
    public function getPlugins(): array
    {
        return $this->properties->plugins ?? self::DEFAULT_PLUGINS;
    }

    private function setAreEventListenersEnabled(?bool $flag): self
    {
        $this->properties->areEventListenersEnabled = $flag ?? self::DEFAULT_ARE_EVENT_LISTENERS_ENABLED;

        return $this;
    }

    private function hasEventListeners(): bool
    {
        return ($this->properties->genericListeners !== null && $this->properties->genericListeners !== [])
            || ($this->properties->specificListeners !== null && $this->properties->specificListeners !== []);
    }

    /**
     * @param ?list<callable> $listeners
     */
    private function setGenericListeners(?array $listeners): self
    {
        $this->properties->genericListeners = $listeners ?? self::DEFAULT_GENERIC_LISTENERS;

        return $this;
    }

    /**
     * @param ?array<string,list<Closure>> $list
     */
    private function setServicesToExtend(?array $list): self
    {
        $this->markPropertyAsChanged(self::servicesToExtend, $list !== null);
        $this->properties->servicesToExtend = $list ?? self::DEFAULT_SERVICES_TO_EXTEND;

        return $this;
    }

    /**
     * @param ?list<class-string> $list
     */
    private function setGacelaConfigsToExtend(?array $list): self
    {
        $this->markPropertyAsChanged(self::gacelaConfigsToExtend, $list !== null);
        $this->properties->gacelaConfigsToExtend = $list ?? self::DEFAULT_GACELA_CONFIGS_TO_EXTEND;

        return $this;
    }

    /**
     * @param ?list<class-string|callable> $list
     */
    private function setPlugins(?array $list): self
    {
        $this->markPropertyAsChanged(self::plugins, $list !== null);
        $this->properties->plugins = $list ?? self::DEFAULT_PLUGINS;

        return $this;
    }

    /**
     * @param ?array<class-string,list<callable>> $listeners
     */
    private function setSpecificListeners(?array $listeners): self
    {
        $this->properties->specificListeners = $listeners ?? self::DEFAULT_SPECIFIC_LISTENERS;

        return $this;
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
