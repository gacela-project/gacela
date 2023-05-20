<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;
use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Event\GacelaEventInterface;

final class GacelaConfig
{
    private AppConfigBuilder $appConfigBuilder;

    private SuffixTypesBuilder $suffixTypesBuilder;

    private BindingsBuilder $bindingsBuilder;

    /** @var array<string, class-string|object|callable> */
    private array $externalServices;

    private ?bool $shouldResetInMemoryCache = null;

    private ?bool $fileCacheEnabled = null;

    private ?string $fileCacheDirectory = null;

    /** @var list<string> */
    private ?array $projectNamespaces = null;

    /** @var array<string,mixed> */
    private ?array $configKeyValues = null;

    private ?bool $areEventListenersEnabled = null;

    /** @var list<callable> */
    private ?array $genericListeners = null;

    /** @var array<class-string,list<callable>> */
    private ?array $specificListeners = null;

    /** @var list<class-string> */
    private ?array $gacelaConfigsToExtend = null;

    /** @var list<class-string|callable> */
    private ?array $plugins = null;

    /** @var array<string,list<Closure>> */
    private array $servicesToExtend = [];

    /**
     * @param array<string,class-string|object|callable> $externalServices
     */
    public function __construct(array $externalServices = [])
    {
        $this->externalServices = $externalServices;
        $this->appConfigBuilder = new AppConfigBuilder();
        $this->suffixTypesBuilder = new SuffixTypesBuilder();
        $this->bindingsBuilder = new BindingsBuilder();
    }

    /**
     * Define 'config/*.php' as path, and 'config/local.php' as local path for the configuration.
     *
     * @return Closure(GacelaConfig):void
     */
    public static function defaultPhpConfig(): callable
    {
        return static function (self $config): void {
            $config->addAppConfig('config/*.php', 'config/local.php');
        };
    }

    /**
     * Define the path where the configuration will be stored.
     *
     * @param string $path define the path where Gacela will read all the config files
     * @param string $pathLocal define the path where Gacela will read the local config file
     * @param class-string<ConfigReaderInterface>|ConfigReaderInterface|null $reader Define the reader class which will read and parse the config files
     */
    public function addAppConfig(string $path, string $pathLocal = '', $reader = null): self
    {
        $this->appConfigBuilder->add($path, $pathLocal, $reader);

        return $this;
    }

    /**
     * Allow overriding gacela facade suffixes.
     */
    public function addSuffixTypeFacade(string $suffix): self
    {
        $this->suffixTypesBuilder->addFacade($suffix);

        return $this;
    }

    /**
     * Allow overriding gacela factory suffixes.
     */
    public function addSuffixTypeFactory(string $suffix): self
    {
        $this->suffixTypesBuilder->addFactory($suffix);

        return $this;
    }

    /**
     * Allow overriding gacela config suffixes.
     */
    public function addSuffixTypeConfig(string $suffix): self
    {
        $this->suffixTypesBuilder->addConfig($suffix);

        return $this;
    }

    /**
     * Allow overriding gacela dependency provider suffixes.
     */
    public function addSuffixTypeDependencyProvider(string $suffix): self
    {
        $this->suffixTypesBuilder->addDependencyProvider($suffix);

        return $this;
    }

    /**
     * @deprecated in favor of `$this->addBinding(key, value)`
     * It will be removed in the next release
     *
     * @param class-string $key
     * @param class-string|object|callable $value
     */
    public function addMappingInterface(string $key, string|object|callable $value): self
    {
        return $this->addBinding($key, $value);
    }

    /**
     * Bind a key class or interface name to be resolved by Gacela automatically.
     *
     * @param class-string $key
     * @param class-string|object|callable $value
     */
    public function addBinding(string $key, string|object|callable $value): self
    {
        $this->bindingsBuilder->bind($key, $value);

        return $this;
    }

    /**
     * Useful to pass services while bootstrapping Gacela to the gacela.php config file.
     *
     * @param class-string|object|callable $value
     */
    public function addExternalService(string $key, $value): self
    {
        $this->externalServices[$key] = $value;

        return $this;
    }

    /**
     * Get an external service from its defined key, previously added using `addExternalService()`.
     *
     * @return class-string|object|callable
     */
    public function getExternalService(string $key)
    {
        return $this->externalServices[$key];
    }

    /**
     * Enable resetting the memory cache on each setup. Useful for functional tests.
     */
    public function resetInMemoryCache(): self
    {
        $this->shouldResetInMemoryCache = true;

        return $this;
    }

    /**
     * Shortcut to setFileCache(true)
     */
    public function enableFileCache(string $dir = null): self
    {
        return $this->setFileCache(true, $dir);
    }

    /**
     * Define whether the file cache flag is enabled,
     * and the file cache directory.
     */
    public function setFileCache(bool $enabled, string $dir = null): self
    {
        $this->fileCacheEnabled = $enabled;
        $this->fileCacheDirectory = $dir;

        return $this;
    }

    /**
     * Define a list of project namespaces.
     *
     * @param list<string> $list
     */
    public function setProjectNamespaces(array $list): self
    {
        $this->projectNamespaces = $list;

        return $this;
    }

    /**
     * Add/replace an existent configuration key with a specific value.
     *
     * @param mixed $value
     */
    public function addAppConfigKeyValue(string $key, $value): self
    {
        $this->configKeyValues[$key] = $value;

        return $this;
    }

    /**
     * Add/replace a list of existent configuration keys with a specific value.
     *
     * @param array<string, mixed> $config
     */
    public function addAppConfigKeyValues(array $config): self
    {
        $this->configKeyValues = array_merge($this->configKeyValues ?? [], $config);

        return $this;
    }

    /**
     * Do not dispatch any event in the application.
     */
    public function disableEventListeners(): self
    {
        $this->areEventListenersEnabled = false;

        return $this;
    }

    /**
     * Register a generic listener when any event happens.
     * The callable argument must be the type `GacelaEventInterface`.
     *
     * @param callable(GacelaEventInterface):void $listener
     */
    public function registerGenericListener(callable $listener): self
    {
        $this->genericListeners ??= [];
        $this->genericListeners[] = $listener;

        return $this;
    }

    /**
     * Register a listener when some event happens.
     *
     * @param class-string $event
     * @param callable(GacelaEventInterface):void $listener
     */
    public function registerSpecificListener(string $event, callable $listener): self
    {
        $this->specificListeners[$event] ??= [];
        $this->specificListeners[$event][] = $listener;

        return $this;
    }

    public function extendService(string $id, Closure $service): self
    {
        $this->servicesToExtend[$id] ??= [];
        $this->servicesToExtend[$id][] = $service;

        return $this;
    }

    /**
     * Add a new invokable class that can extend the GacelaConfig object.
     *
     * This configClass will receive the GacelaConfig object as argument to the __invoke() method.
     * ```
     * __invoke(GacelaConfig $config): void
     * ```
     *
     * @param class-string $className
     */
    public function extendGacelaConfig(string $className): self
    {
        $this->gacelaConfigsToExtend[] = $className;

        return $this;
    }

    /**
     * @param list<class-string> $list
     */
    public function extendGacelaConfigs(array $list): self
    {
        $this->gacelaConfigsToExtend = array_merge($this->gacelaConfigsToExtend ?? [], $list);

        return $this;
    }

    /**
     * @param class-string|callable $plugin
     */
    public function addPlugin(string|callable $plugin): self
    {
        $this->plugins[] = $plugin;

        return $this;
    }

    /**
     * @param list<class-string|callable> $list
     */
    public function addPlugins(array $list): self
    {
        $this->plugins = array_merge($this->plugins ?? [], $list);

        return $this;
    }

    /**
     * @return array{
     *     external-services: array<string,class-string|object|callable>,
     *     app-config-builder: AppConfigBuilder,
     *     suffix-types-builder: SuffixTypesBuilder,
     *     bindings-builder: BindingsBuilder,
     *     should-reset-in-memory-cache: ?bool,
     *     file-cache-enabled: ?bool,
     *     file-cache-directory: ?string,
     *     project-namespaces: ?list<string>,
     *     config-key-values: ?array<string,mixed>,
     *     are-event-listeners-enabled: ?bool,
     *     generic-listeners: ?list<callable>,
     *     specific-listeners: ?array<class-string,list<callable>>,
     *     gacela-configs-to-extend: ?list<class-string>,
     *     plugins: ?list<class-string|callable>,
     *     instances-to-extend: array<string,list<Closure>>,
     * }
     *
     * @internal
     */
    public function build(): array
    {
        return [
            'external-services' => $this->externalServices,
            'app-config-builder' => $this->appConfigBuilder,
            'suffix-types-builder' => $this->suffixTypesBuilder,
            'bindings-builder' => $this->bindingsBuilder,
            'should-reset-in-memory-cache' => $this->shouldResetInMemoryCache,
            'file-cache-enabled' => $this->fileCacheEnabled,
            'file-cache-directory' => $this->fileCacheDirectory,
            'project-namespaces' => $this->projectNamespaces,
            'config-key-values' => $this->configKeyValues,
            'are-event-listeners-enabled' => $this->areEventListenersEnabled,
            'generic-listeners' => $this->genericListeners,
            'specific-listeners' => $this->specificListeners,
            'gacela-configs-to-extend' => $this->gacelaConfigsToExtend,
            'plugins' => $this->plugins,
            'instances-to-extend' => $this->servicesToExtend,
        ];
    }
}
