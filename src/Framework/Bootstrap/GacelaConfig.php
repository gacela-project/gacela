<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;
use Gacela\Container\ContextualBindingBuilder;
use Gacela\Framework\Bootstrap\Setup\GacelaConfigTransfer;
use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Health\HealthCheckRegistry;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use InvalidArgumentException;

use function array_key_exists;
use function is_array;
use function sprintf;

/**
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 * @psalm-import-type ExternalServicesMap from BuilderConfigurationInterface
 * @psalm-import-type ServiceFactoryMap from ContainerConfigurationInterface
 * @psalm-import-type ServiceAliasMap from ContainerConfigurationInterface
 * @psalm-import-type ConfigKeyValues from SetupGacelaInterface
 * @psalm-import-type ServicesToExtendMap from ContainerConfigurationInterface
 * @psalm-import-type HandlerRegistriesMap from ContainerConfigurationInterface
 * @psalm-import-type TagsMap from ContainerConfigurationInterface
 * @psalm-import-type AfterResolvingMap from ContainerConfigurationInterface
 * @psalm-import-type DefinitionSources from ContainerConfigurationInterface
 * @psalm-import-type ContextualBindingsMap from ContainerConfigurationInterface
 * @psalm-import-type SpecificListenersMap from \Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher
 */
final class GacelaConfig
{
    private readonly AppConfigBuilder $appConfigBuilder;

    private readonly SuffixTypesBuilder $suffixTypesBuilder;

    private readonly BindingsBuilder $bindingsBuilder;

    private ?bool $shouldResetInMemoryCache = null;

    private ?bool $fileCacheEnabled = null;

    private ?string $fileCacheDirectory = null;

    /** @var list<string> */
    private ?array $projectNamespaces = null;

    /** @var list<string> */
    private ?array $appModulePaths = null;

    /** @var ConfigKeyValues */
    private ?array $configKeyValues = null;

    private ?bool $areEventListenersEnabled = null;

    /** @var list<callable> */
    private ?array $genericListeners = null;

    /** @var SpecificListenersMap */
    private ?array $specificListeners = null;

    /** @var list<class-string> */
    private ?array $gacelaConfigsToExtend = null;

    /** @var list<class-string|callable> */
    private ?array $plugins = null;

    /** @var ServicesToExtendMap */
    private array $servicesToExtend = [];

    /** @var ServiceFactoryMap */
    private array $factories = [];

    /** @var ServiceFactoryMap */
    private array $protectedServices = [];

    /** @var ServiceAliasMap */
    private array $aliases = [];

    /** @var ContextualBindingsMap */
    private array $contextualBindings = [];

    /** @var HandlerRegistriesMap */
    private array $handlerRegistries = [];

    /** @var TagsMap */
    private array $tags = [];

    /** @var AfterResolvingMap */
    private array $afterResolvingCallbacks = [];

    /** @var ServiceFactoryMap */
    private array $lazyServices = [];

    /** @var DefinitionSources */
    private array $definitions = [];

    /**
     * @param ExternalServicesMap $externalServices
     */
    public function __construct(private array $externalServices = [])
    {
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
    public function addAppConfig(string $path, string $pathLocal = '', string|ConfigReaderInterface|null $reader = null): self
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
    public function addSuffixTypeProvider(string $suffix): self
    {
        $this->suffixTypesBuilder->addProvider($suffix);

        return $this;
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
     * Bind a key only when it is not already bound in this config, so plugins can
     * register a default that the application (or an earlier binding) can override.
     *
     * @param class-string $key
     * @param class-string|object|callable $value
     */
    public function addBindingIf(string $key, string|object|callable $value): self
    {
        $this->bindingsBuilder->bindIf($key, $value);

        return $this;
    }

    /**
     * Useful to pass services while bootstrapping Gacela to the gacela.php config file.
     *
     * @param class-string|object|callable $value
     */
    public function addExternalService(string $key, string|object|callable $value): self
    {
        $this->externalServices[$key] = $value;

        return $this;
    }

    /**
     * Get an external service from its defined key, previously added using `addExternalService()`.
     *
     * @return class-string|object|callable
     */
    public function getExternalService(string $key): string|object|callable
    {
        if (!array_key_exists($key, $this->externalServices)) {
            $availableKeys = $this->externalServices === []
                ? 'none'
                : implode(', ', array_keys($this->externalServices));

            throw new InvalidArgumentException(
                sprintf('External service "%s" not found. Available keys: %s', $key, $availableKeys),
            );
        }

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
    public function enableFileCache(?string $dir = null): self
    {
        return $this->setFileCache(true, $dir);
    }

    /**
     * Define whether the file cache flag is enabled,
     * and the file cache directory.
     */
    public function setFileCache(bool $enabled, ?string $dir = null): self
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
     * Restrict which directories are scanned when discovering application modules
     * (used by console commands: list:modules, debug:modules, cache:warm, doctor).
     *
     * Paths can be absolute or relative to the application root. Missing paths are
     * skipped with a warning at scan time. When unset, scanning walks the entire
     * application root directory.
     *
     * @param list<string> $paths
     */
    public function setAppModulePaths(array $paths): self
    {
        $this->appModulePaths = $paths;

        return $this;
    }

    /**
     * Add/replace an existent configuration key with a specific value.
     */
    public function addAppConfigKeyValue(string $key, mixed $value): self
    {
        $this->configKeyValues ??= [];
        $this->configKeyValues[$key] = $value;

        return $this;
    }

    /**
     * Add/replace a list of existent configuration keys with a specific value.
     *
     * @param ConfigKeyValues $config
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
     * Run a callback on an instance after the container builds it, receiving the
     * instance and the container. Callbacks run in registration order.
     *
     * Example:
     * ```php
     * $config->afterResolving(
     *     LoggerAwareInterface::class,
     *     static fn (LoggerAwareInterface $s) => $s->setLogger($logger),
     * );
     * ```
     *
     * `$id` may name an **interface**, which is the point: the match is made
     * against the resolved instance, not by looking the requested id up in a
     * map, so one registration covers every implementation. A concrete class
     * name works too and matches only that class.
     *
     * Distinct from the two adjacent tools, which solve different problems:
     * {@see extendService()} *replaces* what comes out, so it is right for
     * decoration and wrong for "call a setter on every instance of this"; the
     * event listeners *observe*, and `BindingRegisteredEvent` fires at
     * registration rather than at resolution.
     *
     * Hooks fire on container-level resolution -- `get()`, `getOrFail()` and
     * `make()`. A class the inner container autowires as a nested constructor
     * dependency is not resolved at this level, so hooks do not fire for it.
     *
     * A callback that throws removes the instance from the container rather
     * than leaving a half-wired one behind for the next caller.
     *
     * @param string $id a concrete class, or an interface every implementation of which should match
     * @param Closure $callback receives (object $instance, Container $container)
     */
    public function afterResolving(string $id, Closure $callback): self
    {
        $this->afterResolvingCallbacks[$id] ??= [];
        $this->afterResolvingCallbacks[$id][] = $callback;

        return $this;
    }

    /**
     * Register a factory service that creates a new instance on each call.
     * Unlike regular services (which are singletons), factory services return
     * a new instance every time they are resolved from the container.
     *
     * The `$id` is usually a class name or interface.
     *
     * @return $this
     */
    public function addFactory(string $id, Closure $factory): self
    {
        $this->factories[$id] = $factory;

        return $this;
    }

    /**
     * Register a protected service that cannot be extended.
     * Protected services are stored as closures and won't be invoked by the container,
     * making them useful for storing callable configurations.
     *
     * @return $this
     */
    public function addProtected(string $id, Closure $service): self
    {
        $this->protectedServices[$id] = $service;

        return $this;
    }

    /**
     * Create an alias for a service.
     * This allows you to reference the same service with different names.
     *
     * @return $this
     */
    public function addAlias(string $alias, string $id): self
    {
        $this->aliases[$alias] = $id;

        return $this;
    }

    /**
     * Register a lazy-loaded service that is only instantiated when first accessed,
     * deferring expensive service creation until it is actually needed.
     *
     * Example:
     * ```php
     * $config->addLazy(ExpensiveService::class, function(ContainerInterface $c) {
     *     return new ExpensiveService($c->get(Dependency::class));
     * });
     * ```
     *
     * @param string $id The service identifier
     * @param Closure $factory The factory closure that creates the service when needed
     *
     * @return $this
     */
    public function addLazy(string $id, Closure $factory): self
    {
        $this->lazyServices[$id] = $factory;

        return $this;
    }

    /**
     * Register a whole set of bindings from *data* rather than from a closure,
     * so wiring that is generated, shared between environments, or diffed in
     * review does not have to be written as a sequence of method calls.
     *
     * Pass the definitions inline, or the path of a '.php' file returning an
     * array or a '.json' file holding an object:
     * ```php
     * $config->loadDefinitions([
     *     LoggerInterface::class => FileLogger::class,
     *     Database::class => ['singleton' => DatabasePool::class],
     *     'db.dsn' => ['value' => 'pgsql://localhost/app'],
     * ]);
     * $config->loadDefinitions(__DIR__ . '/services.json');
     * ```
     *
     * Applies app-wide, reaching every module's container, which is how
     * {@see addBinding()} already behaves. A module that wants definitions of
     * its own calls `Container::load()` in its Provider, keeping them local to
     * that module's container.
     *
     * Every entry ends up calling the registration method it stands for, so a
     * definition behaves exactly like the imperative call it replaces. Sources
     * are applied in the order declared, and *after* the imperative
     * registrations: a later source overrides an earlier one, and a definitions
     * file overrides `addBinding()`, which is what a per-environment override
     * file is loaded to do. Tags accumulate instead of overriding.
     *
     * The path is used as given -- unlike {@see enableFileCache()} it is not
     * rebased under the application root, so write it with `__DIR__`. A missing
     * or unparsable file throws rather than leaving the wiring half-applied.
     *
     * YAML is deliberately not supported: there is no parser in the container,
     * and neither it nor Gacela takes a second runtime dependency for one. Pass
     * `Yaml::parseFile('services.yaml')` as the array instead.
     *
     * @param string|array<array-key, mixed> $definitions a definitions array, or the path of a '.php'/'.json' file holding one
     */
    public function loadDefinitions(string|array $definitions): self
    {
        $this->definitions[] = $definitions;

        return $this;
    }

    /**
     * Define contextual bindings - different implementations based on the requesting class.
     * This allows you to provide different implementations of an interface depending on
     * which class is requesting it.
     *
     * Example:
     * ```php
     * $config->when(UserController::class)
     *     ->needs(LoggerInterface::class)
     *     ->give(FileLogger::class);
     * ```
     *
     * @param class-string|list<class-string> $concrete The class(es) that need the binding
     */
    public function when(string|array $concrete): ContextualBindingBuilder
    {
        $builder = new ContextualBindingBuilder($this->contextualBindings);
        $builder->when($concrete);

        return $builder;
    }

    /**
     * Declare a build-time dispatch table. The registry is resolvable from the
     * container under `$registryKey` and returns a {@see \Gacela\Framework\Plugins\HandlerRegistry}
     * that lazy-instantiates each handler through the container on first access.
     *
     * Registries are frozen after boot: there is no runtime `register()` method.
     *
     * @param string $registryKey identifier under which the registry is resolved (typically an interface/class name)
     * @param array<string|int,class-string> $handlers map of dispatch key to handler class
     */
    public function addHandlerRegistry(string $registryKey, array $handlers): self
    {
        $this->handlerRegistries[$registryKey] = $handlers;

        return $this;
    }

    /**
     * Group service identifiers under a label so a module can ask for "every
     * service tagged X" without knowing who registered them. Resolve the group
     * with `Container::tagged($tag)`, which instantiates each id lazily, in the
     * order it was tagged.
     *
     * Example:
     * ```php
     * $config->tag([EmailValidator::class, SmsValidator::class], 'validators');
     *
     * // in a Provider
     * $container->set('validators', static fn (Container $c) => $c->tagged('validators'));
     * ```
     *
     * Declared here, a tag reaches every module's container, so any module can
     * consume it. A module that wants to *add* to a tag calls `Container::tag()`
     * in its own Provider: that stays local to that module's container, which is
     * what keeps one module's contribution from leaking into a sibling's.
     *
     * Complementary to {@see addHandlerRegistry()}, not a replacement for it: a
     * registry is keyed and answers "the handler for this key" (a command bus);
     * a tag is unkeyed and answers "every implementation of this" (validators,
     * listeners). See docs/getting-a-dependency.md.
     *
     * Repeated calls add to the tag rather than replacing it. An id tagged twice
     * is only yielded once -- deduplication is left to the container's tag
     * registry, which does it anyway, rather than done again here.
     *
     * @param string|list<string> $ids one identifier, or several, to put under the tag
     * @param string $tag the label the group is resolved by
     */
    public function tag(string|array $ids, string $tag): self
    {
        foreach (is_array($ids) ? $ids : [$ids] as $id) {
            $this->tags[$tag][] = $id;
        }

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
        $this->gacelaConfigsToExtend ??= [];
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
        $this->plugins ??= [];
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
     * Register a module health check to be executed by the Doctor command.
     *
     * @param class-string<ModuleHealthCheckInterface>|ModuleHealthCheckInterface $check
     */
    public function addHealthCheck(string|ModuleHealthCheckInterface $check): self
    {
        HealthCheckRegistry::register($check);

        return $this;
    }

    /**
     * @internal
     */
    public function toTransfer(): GacelaConfigTransfer
    {
        return new GacelaConfigTransfer(
            $this->appConfigBuilder,
            $this->suffixTypesBuilder,
            $this->bindingsBuilder,
            $this->externalServices,
            $this->shouldResetInMemoryCache,
            $this->fileCacheEnabled,
            $this->fileCacheDirectory,
            $this->projectNamespaces,
            $this->appModulePaths,
            $this->configKeyValues,
            $this->genericListeners,
            $this->specificListeners,
            $this->areEventListenersEnabled,
            $this->gacelaConfigsToExtend,
            $this->plugins,
            $this->servicesToExtend,
            $this->factories,
            $this->protectedServices,
            $this->aliases,
            $this->contextualBindings,
            $this->handlerRegistries,
            $this->lazyServices,
            $this->tags,
            $this->afterResolvingCallbacks,
            $this->definitions,
        );
    }

}
