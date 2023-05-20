<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use RuntimeException;

use function is_callable;

/**
 * @psalm-suppress ArgumentTypeCoercion,MixedArgumentTypeCoercion
 */
final class SetupGacela extends AbstractSetupGacela
{
    public const shouldResetInMemoryCache = 'shouldResetInMemoryCache';
    public const fileCacheEnabled = 'fileCacheEnabled';
    public const fileCacheDirectory = 'fileCacheDirectory';
    public const externalServices = 'externalServices';
    public const projectNamespaces = 'projectNamespaces';
    public const configKeyValues = 'configKeyValues';
    public const servicesToExtend = 'servicesToExtend';
    public const plugins = 'plugins';
    public const gacelaConfigsToExtend = 'gacelaConfigsToExtend';

    private const DEFAULT_ARE_EVENT_LISTENERS_ENABLED = true;
    private const DEFAULT_SHOULD_RESET_IN_MEMORY_CACHE = false;
    private const DEFAULT_FILE_CACHE_ENABLED = GacelaFileCache::DEFAULT_ENABLED_VALUE;
    private const DEFAULT_FILE_CACHE_DIRECTORY = GacelaFileCache::DEFAULT_DIRECTORY_VALUE;
    private const DEFAULT_PROJECT_NAMESPACES = [];
    private const DEFAULT_CONFIG_KEY_VALUES = [];
    private const DEFAULT_GENERIC_LISTENERS = [];
    private const DEFAULT_SPECIFIC_LISTENERS = [];
    private const DEFAULT_SERVICES_TO_EXTEND = [];
    private const DEFAULT_GACELA_CONFIGS_TO_EXTEND = [];
    private const DEFAULT_PLUGINS = [];

    /** @var callable(AppConfigBuilder):void */
    private $appConfigFn;

    /** @var callable(BindingsBuilder,array<string,mixed>):void */
    private $bindingsFn;

    /** @var callable(SuffixTypesBuilder):void */
    private $suffixTypesFn;

    /** @var ?array<string,class-string|object|callable> */
    private ?array $externalServices = null;

    private ?AppConfigBuilder $appConfigBuilder = null;

    private ?SuffixTypesBuilder $suffixTypesBuilder = null;

    private ?BindingsBuilder $bindingsBuilder = null;

    private ?bool $shouldResetInMemoryCache = null;

    private ?bool $fileCacheEnabled = null;

    private ?string $fileCacheDirectory = null;

    /** @var ?list<string> */
    private ?array $projectNamespaces = null;

    /** @var ?array<string,mixed> */
    private ?array $configKeyValues = null;

    private ?bool $areEventListenersEnabled = null;

    /** @var ?list<callable> */
    private ?array $genericListeners = null;

    /** @var ?array<class-string,list<callable>> */
    private ?array $specificListeners = null;

    private ?EventDispatcherInterface $eventDispatcher = null;

    /** @var ?array<string,bool> */
    private ?array $changedProperties = null;

    /** @var ?array<string,list<Closure>> */
    private ?array $servicesToExtend = null;

    /** @var ?list<class-string> */
    private ?array $gacelaConfigsToExtend = null;

    /** @var ?list<class-string|callable> */
    private ?array $plugins = null;

    public function __construct()
    {
        $emptyFn = static function (): void {
        };

        $this->appConfigFn = $emptyFn;
        $this->bindingsFn = $emptyFn;
        $this->suffixTypesFn = $emptyFn;
    }

    public static function fromFile(string $gacelaFilePath): self
    {
        if (!is_file($gacelaFilePath)) {
            throw new RuntimeException("Invalid file path: '{$gacelaFilePath}'");
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
        self::runExtendConfig($gacelaConfig);

        return self::fromGacelaConfig($gacelaConfig);
    }

    public static function fromGacelaConfig(GacelaConfig $gacelaConfig): self
    {
        $build = $gacelaConfig->build();

        return (new self())
            ->setExternalServices($build['external-services'])
            ->setAppConfigBuilder($build['app-config-builder'])
            ->setSuffixTypesBuilder($build['suffix-types-builder'])
            ->setBindingsBuilder($build['bindings-builder'])
            ->setShouldResetInMemoryCache($build['should-reset-in-memory-cache'])
            ->setFileCacheEnabled($build['file-cache-enabled'])
            ->setFileCacheDirectory($build['file-cache-directory'])
            ->setProjectNamespaces($build['project-namespaces'])
            ->setConfigKeyValues($build['config-key-values'])
            ->setAreEventListenersEnabled($build['are-event-listeners-enabled'])
            ->setGenericListeners($build['generic-listeners'])
            ->setSpecificListeners($build['specific-listeners'])
            ->setGacelaConfigsToExtend($build['gacela-configs-to-extend'])
            ->setPlugins($build['plugins'])
            ->setServicesToExtend($build['instances-to-extend']);
    }

    /**
     * @param array<string,class-string|object|callable> $array
     */
    public function setExternalServices(array $array): self
    {
        $this->markPropertyChanged(self::externalServices, true);
        $this->externalServices = $array;

        return $this;
    }

    public function setAppConfigBuilder(AppConfigBuilder $builder): self
    {
        $this->appConfigBuilder = $builder;

        return $this;
    }

    public function setSuffixTypesBuilder(SuffixTypesBuilder $builder): self
    {
        $this->suffixTypesBuilder = $builder;

        return $this;
    }

    public function setBindingsBuilder(BindingsBuilder $builder): self
    {
        $this->bindingsBuilder = $builder;

        return $this;
    }

    /**
     * @param callable(AppConfigBuilder):void $callable
     */
    public function setAppConfigFn(callable $callable): self
    {
        $this->appConfigFn = $callable;

        return $this;
    }

    public function buildAppConfig(AppConfigBuilder $builder): AppConfigBuilder
    {
        $builder = parent::buildAppConfig($builder);

        if ($this->appConfigBuilder) {
            $builder = $this->appConfigBuilder;
        }

        ($this->appConfigFn)($builder);

        return $builder;
    }

    /**
     * @param callable(BindingsBuilder,array<string,mixed>):void $callable
     */
    public function setBindingsFn(callable $callable): self
    {
        $this->bindingsFn = $callable;

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

        if ($this->bindingsBuilder) {
            $builder = $this->bindingsBuilder;
        }

        ($this->bindingsFn)(
            $builder,
            array_merge($this->externalServices ?? [], $externalServices)
        );

        return $builder;
    }

    /**
     * @param callable(SuffixTypesBuilder):void $callable
     */
    public function setSuffixTypesFn(callable $callable): self
    {
        $this->suffixTypesFn = $callable;

        return $this;
    }

    /**
     * Allow overriding gacela resolvable types.
     */
    public function buildSuffixTypes(SuffixTypesBuilder $builder): SuffixTypesBuilder
    {
        $builder = parent::buildSuffixTypes($builder);

        if ($this->suffixTypesBuilder) {
            $builder = $this->suffixTypesBuilder;
        }

        ($this->suffixTypesFn)($builder);

        return $builder;
    }

    /**
     * @return array<string, class-string|object|callable>
     */
    public function externalServices(): array
    {
        return array_merge(
            parent::externalServices(),
            $this->externalServices ?? [],
        );
    }

    public function setShouldResetInMemoryCache(?bool $flag): self
    {
        $this->markPropertyChanged(self::shouldResetInMemoryCache, $flag);
        $this->shouldResetInMemoryCache = $flag ?? self::DEFAULT_SHOULD_RESET_IN_MEMORY_CACHE;

        return $this;
    }

    public function shouldResetInMemoryCache(): bool
    {
        return (bool)$this->shouldResetInMemoryCache;
    }

    public function isFileCacheEnabled(): bool
    {
        return (bool)$this->fileCacheEnabled;
    }

    public function getFileCacheDirectory(): string
    {
        return (string)$this->fileCacheDirectory;
    }

    public function setFileCacheDirectory(?string $dir): self
    {
        $this->markPropertyChanged(self::fileCacheDirectory, $dir);
        $this->fileCacheDirectory = $dir ?? self::DEFAULT_FILE_CACHE_DIRECTORY;

        return $this;
    }

    /**
     * @param ?list<string> $list
     */
    public function setProjectNamespaces(?array $list): self
    {
        $this->markPropertyChanged(self::projectNamespaces, $list);
        $this->projectNamespaces = $list ?? self::DEFAULT_PROJECT_NAMESPACES;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getProjectNamespaces(): array
    {
        return (array)$this->projectNamespaces;
    }

    /**
     * @return array<string,mixed>
     */
    public function getConfigKeyValues(): array
    {
        return (array)$this->configKeyValues;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher ??= SetupEventDispatcher::getDispatcher($this);
    }

    /**
     * @return array<string,list<Closure>>
     */
    public function getServicesToExtend(): array
    {
        return (array)$this->servicesToExtend;
    }

    public function setFileCacheEnabled(?bool $flag): self
    {
        $this->markPropertyChanged(self::fileCacheEnabled, $flag);
        $this->fileCacheEnabled = $flag ?? self::DEFAULT_FILE_CACHE_ENABLED;

        return $this;
    }

    public function canCreateEventDispatcher(): bool
    {
        return $this->areEventListenersEnabled
            && $this->hasEventListeners();
    }

    /**
     * @param ?array<string,mixed> $configKeyValues
     */
    public function setConfigKeyValues(?array $configKeyValues): self
    {
        $this->markPropertyChanged(self::configKeyValues, $configKeyValues);
        $this->configKeyValues = $configKeyValues ?? self::DEFAULT_CONFIG_KEY_VALUES;

        return $this;
    }

    /**
     * @return array<class-string,list<callable>>|null
     */
    public function getSpecificListeners(): ?array
    {
        return $this->specificListeners;
    }

    /**
     * @return list<callable>|null
     */
    public function getGenericListeners(): ?array
    {
        return $this->genericListeners;
    }

    public function isPropertyChanged(string $name): bool
    {
        return $this->changedProperties[$name] ?? false;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    public function combine(self $other): self
    {
        return (new SetupCombinator($this))->combine($other);
    }

    /**
     * @param list<Closure> $servicesToExtend
     */
    public function addServicesToExtend(string $serviceId, array $servicesToExtend): self
    {
        $this->servicesToExtend[$serviceId] ??= [];
        $this->servicesToExtend[$serviceId] = array_merge(
            $this->servicesToExtend[$serviceId],
            $servicesToExtend,
        );

        return $this;
    }

    public function combineExternalServices(array $list): void
    {
        $this->setExternalServices(array_merge($this->externalServices ?? [], $list));
    }

    public function combineProjectNamespaces(array $list): void
    {
        $this->setProjectNamespaces(array_merge($this->projectNamespaces ?? [], $list));
    }

    public function combineConfigKeyValues(array $list): void
    {
        $this->setConfigKeyValues(array_merge($this->configKeyValues ?? [], $list));
    }

    /**
     * @param list<class-string> $list
     */
    public function combineGacelaConfigsToExtend(array $list): void
    {
        $this->setGacelaConfigsToExtend(array_merge($this->gacelaConfigsToExtend ?? [], $list));
    }

    /**
     * @param list<class-string|callable> $list
     */
    public function combinePlugins(array $list): void
    {
        $this->setPlugins(array_merge($this->plugins ?? [], $list));
    }

    /**
     * @return list<class-string>
     */
    public function getGacelaConfigsToExtend(): array
    {
        return (array)$this->gacelaConfigsToExtend;
    }

    /**
     * @return list<class-string|callable>
     */
    public function getPlugins(): array
    {
        return (array)$this->plugins;
    }

    private static function runExtendConfig(GacelaConfig $gacelaConfig): void
    {
        $configsToExtend = $gacelaConfig->build()['gacela-configs-to-extend'] ?? [];

        if ($configsToExtend === []) {
            return;
        }

        $container = new Container();

        foreach ($configsToExtend as $className) {
            /** @var callable $configToExtend */
            $configToExtend = $container->get($className);
            $configToExtend($gacelaConfig);
        }
    }

    private function setAreEventListenersEnabled(?bool $flag): self
    {
        $this->areEventListenersEnabled = $flag ?? self::DEFAULT_ARE_EVENT_LISTENERS_ENABLED;

        return $this;
    }

    private function hasEventListeners(): bool
    {
        return !empty($this->genericListeners)
            || !empty($this->specificListeners);
    }

    /**
     * @param ?list<callable> $listeners
     */
    private function setGenericListeners(?array $listeners): self
    {
        $this->genericListeners = $listeners ?? self::DEFAULT_GENERIC_LISTENERS;

        return $this;
    }

    /**
     * @param ?array<string,list<Closure>> $list
     */
    private function setServicesToExtend(?array $list): self
    {
        $this->markPropertyChanged(self::servicesToExtend, $list);
        $this->servicesToExtend = $list ?? self::DEFAULT_SERVICES_TO_EXTEND;

        return $this;
    }

    /**
     * @param ?list<class-string> $list
     */
    private function setGacelaConfigsToExtend(?array $list): self
    {
        $this->markPropertyChanged(self::gacelaConfigsToExtend, $list);
        $this->gacelaConfigsToExtend = $list ?? self::DEFAULT_GACELA_CONFIGS_TO_EXTEND;

        return $this;
    }

    /**
     * @param ?list<class-string|callable> $list
     */
    private function setPlugins(?array $list): self
    {
        $this->markPropertyChanged(self::plugins, $list);
        $this->plugins = $list ?? self::DEFAULT_PLUGINS;

        return $this;
    }

    /**
     * @param ?array<class-string,list<callable>> $listeners
     */
    private function setSpecificListeners(?array $listeners): self
    {
        $this->specificListeners = $listeners ?? self::DEFAULT_SPECIFIC_LISTENERS;

        return $this;
    }

    private function markPropertyChanged(string $name, mixed $value): void
    {
        $this->changedProperties[$name] = ($value !== null);
    }
}
