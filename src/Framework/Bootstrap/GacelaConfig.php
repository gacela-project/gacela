<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;
use Gacela\Container\ContextualBindingBuilder;
use Gacela\Framework\Bootstrap\Setup\GacelaConfigTransfer;
use Gacela\Framework\ClassResolver\ResolvableTypes;
use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Config\Schema\ConfigType;
use Gacela\Framework\Dto\Schema\DtoType;
use Gacela\Framework\Dto\Schema\MalformedDtoSchemaException;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\PsrEventDispatcherAdapter;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Health\HealthCheckRegistry;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

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
 * @psalm-import-type PluginStacksMap from ContainerConfigurationInterface
 * @psalm-import-type ProviderServicesToExtendMap from ContainerConfigurationInterface
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

    private ?EventDispatcherInterface $eventDispatcher = null;

    /** @var list<string> */
    private ?array $projectNamespaces = null;

    /** @var list<string> */
    private ?array $configDimensions = null;

    /** @var list<string> */
    private ?array $appModulePaths = null;

    /** @var list<string> */
    private ?array $dontDiscover = null;

    /** @var ConfigKeyValues */
    private ?array $configKeyValues = null;

    /** @var ?array<string, ConfigType> */
    private ?array $configSchema = null;

    /** @var ?array<string, array<string, DtoType>> */
    private ?array $dtoSchema = null;

    private ?bool $shouldValidateConfigSchemaOnBoot = null;

    private ?string $stubsDir = null;

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

    /** @var ProviderServicesToExtendMap */
    private array $providerServicesToExtend = [];

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

    /** @var PluginStacksMap */
    private array $pluginStacks = [];

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
     * Declare a class kind this project resolves by suffix, beyond the four
     * pillars.
     *
     * A declared kind behaves like a pillar from then on: the finder tries
     * `Report\ReportExporter` and `Report\Exporter` through the same rules and
     * namespaces, the file cache holds it, and `doctor` knows its suffixes.
     * Suffixes default to the kind's own name; a suffix another kind already
     * claims is rejected here, at bootstrap.
     *
     * ```php
     * $config->addResolvableType('Exporter', AbstractExporter::class, ['Exporter', 'Feed']);
     * ```
     *
     * @param class-string|null $abstractClass the base a class of this kind extends
     * @param list<string> $suffixes defaults to the kind's own name
     */
    public function addResolvableType(string $kind, ?string $abstractClass = null, array $suffixes = []): self
    {
        $this->suffixTypesBuilder->declareType($kind, $abstractClass, $suffixes);

        return $this;
    }

    /**
     * Allow overriding gacela facade suffixes.
     */
    public function addSuffixTypeFacade(string $suffix): self
    {
        return $this->addResolvableType(ResolvableTypes::FACADE, null, [$suffix]);
    }

    /**
     * Allow overriding gacela factory suffixes.
     */
    public function addSuffixTypeFactory(string $suffix): self
    {
        return $this->addResolvableType(ResolvableTypes::FACTORY, null, [$suffix]);
    }

    /**
     * Allow overriding gacela config suffixes.
     */
    public function addSuffixTypeConfig(string $suffix): self
    {
        return $this->addResolvableType(ResolvableTypes::CONFIG, null, [$suffix]);
    }

    /**
     * Allow overriding gacela dependency provider suffixes.
     */
    public function addSuffixTypeProvider(string $suffix): self
    {
        return $this->addResolvableType(ResolvableTypes::PROVIDER, null, [$suffix]);
    }

    /**
     * Declare an environment variable that selects configuration, the way
     * `APP_ENV` already does.
     *
     * ```php
     * $config->addConfigDimension('APP_REGION');
     * $config->addConfigDimension('APP_TENANT');
     * ```
     *
     * With `APP_ENV=prod APP_REGION=eu`, `config/*.php` is read, then
     * `config/*-prod.php`, then `config/*-prod-eu.php`; each layer refines the
     * one before it, so more specific wins. Declaration order is the order of
     * the chain, and a variable that is unset ends it -- with no region, a
     * tenant is never consulted, because `app-prod--acme.php` would be a file
     * with a hole in it and no meaning.
     *
     * The base pattern excludes the files it would otherwise read twice:
     * `config/*.php` also matches `app-prod.php`, and a match named after
     * another match plus one or more trailing `-<segment>` parts is that file's
     * layer rather than part of the base. So a key only one environment sets is
     * genuinely absent from the others, which is what makes "each layer refines
     * the one before it" true rather than a description of the sort order.
     * {@see \Gacela\Framework\Config\EnvironmentLayer}
     *
     * A value may contain only letters, digits, `_`, `.` and `-`: it reaches a
     * glob pattern and a cache filename, so anything else is refused at
     * bootstrap rather than resolving somewhere unintended.
     */
    public function addConfigDimension(string $environmentVariable): self
    {
        $this->configDimensions[] = $environmentVariable;

        return $this;
    }

    /**
     * Bind a key class or interface name to be resolved by Gacela automatically.
     *
     * Bound *by type*, which is what autowiring matches a constructor parameter
     * against -- so this and {@see loadDefinitions()} are the two verbs that can
     * fill one, wherever it appears: a nested dependency, a module's services,
     * or the constructor of a Facade, Factory, Config or Provider.
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
     * "In this config" is the whole of it: the check runs against the bindings on
     * this instance, not against every config source. `extendGacelaConfig()` is
     * what makes the promise work, because extending configs run against the same
     * instance -- so a package registering its default there sees what the
     * application already bound and declines.
     *
     * Between separate sources there is nothing to see yet, and the usual merge
     * order decides instead: `gacela.php` merges onto the bootstrap closure, so a
     * conditional binding in `gacela.php` still replaces an unconditional one in
     * the closure, exactly as a plain {@see addBinding()} there would.
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
     * Reset the in-memory caches -- including the locator and its instance
     * cache -- as part of this bootstrap. Anything that re-bootstraps inside
     * one process wants this: functional tests, and both bridges call it on
     * every boot, or a re-bootstrap keeps serving the previous boot's
     * services out of the stale locator (#666).
     */
    public function resetInMemoryCache(): self
    {
        $this->shouldResetInMemoryCache = true;

        return $this;
    }

    /**
     * Shortcut to setFileCache(true)
     *
     * `$dir` is resolved **relative to the app root**, and a leading `/` does
     * not escape it -- `'/var/cache'` writes to `{appRoot}/var/cache`. The
     * exceptions are a path already inside the app root, and a Windows
     * drive-letter path, both of which are taken as given. For a directory
     * genuinely outside the project, use `GACELA_CACHE_DIR`: it is read first,
     * taken verbatim, and means the same thing on either platform.
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
     * Refuse the Gacela configuration an installed package declares in its
     * `composer.json` under `extra.gacela.config`.
     *
     * A discovered config is arbitrary PHP, executed during
     * `Gacela::bootstrap()` in the same process as the application -- exactly
     * like a Laravel service provider registered through `extra.laravel`. This
     * is the control over it. Naming a package here means its file is never
     * read, not that its effects are undone afterwards.
     *
     * ```php
     * $config->dontDiscover(['acme/legacy-invoicing']);
     * $config->dontDiscover(['*']); // no package config is read at all
     * ```
     *
     * Names are the Composer package names as `composer.json` writes them,
     * matched exactly. `'*'` refuses every package, including ones installed
     * later, and is the switch to reach for when a project wants nothing but
     * its own `gacela.php` deciding what runs at boot.
     *
     * Read from the bootstrap closure and from `gacela.php`, and merged across
     * both. It cannot be read from `gacela-{env}.php`: an environment file is
     * merged *after* the packages, so an opt-out written there would arrive
     * once the code it refuses had already run. `doctor` reports one written
     * there rather than letting it look effective.
     *
     * @param list<string> $packages
     */
    public function dontDiscover(array $packages): self
    {
        $this->dontDiscover = $packages;

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
     * Declare what the application's configuration is supposed to contain.
     *
     * ```php
     * $config->declareConfigSchema([
     *     'db.dsn'  => ConfigType::string()->required(),
     *     'retries' => ConfigType::int()->default(3),
     * ]);
     * ```
     *
     * Nothing is checked while booting: the declaration is what
     * `validate:config` and `doctor` read, so a missing key fails a command
     * rather than a request in whichever environment lacked it. Declared
     * defaults *are* applied, because a key with a default is not missing.
     *
     * Called twice, or reached again through `extendGacelaConfig()`, the
     * declarations merge per key and the later one wins.
     *
     * @param array<string, ConfigType> $schema
     */
    public function declareConfigSchema(array $schema): self
    {
        $this->configSchema = array_merge($this->configSchema ?? [], $schema);

        return $this;
    }

    /**
     * Declare a data shape, by the class it generates.
     *
     * ```php
     * $config->declareDtoSchema(App\Checkout\Order::class, [
     *     'reference'  => DtoType::string()->required(),
     *     'total'      => DtoType::int()->required()->describe('order total in cents'),
     *     'couponCode' => DtoType::string(),
     * ]);
     * ```
     *
     * `vendor/bin/gacela dto:generate` writes the class: final, typed getters,
     * `with*()` copies, `toArray()` and `fromArray()`. Nothing is generated
     * while booting -- the declaration is only read by that command.
     *
     * The id is the fully qualified class name on purpose. The file is written
     * where your own composer `autoload` already looks for that namespace, so
     * the generated class needs no autoloader from the framework and no step
     * before static analysis can see it.
     *
     * Reached again through `extendGacelaConfig()`, declarations of one class
     * **union**: another declarer may add properties, which is how a project
     * extends a packaged shape without forking its file. Redeclaring a property
     * differently throws, because the first declarer's code reads the same
     * generated class.
     *
     * @param class-string|string $className
     * @param array<string, DtoType> $properties
     */
    public function declareDtoSchema(string $className, array $properties): self
    {
        $declared = $this->dtoSchema ?? [];

        foreach ($properties as $property => $type) {
            // The same rule `PropertyMerger::mergeDtoSchema()` applies when two
            // sources meet. Enforced here too, because the reason for it does
            // not depend on where the second declaration sits: this accumulator
            // used to overwrite, so one source declaring a class twice changed
            // the generated class under everyone already reading it.
            $existing = $declared[$className][$property] ?? null;

            if ($existing instanceof DtoType && !$existing->isSameShapeAs($type)) {
                throw MalformedDtoSchemaException::conflictingRedeclaration($className, $property);
            }

            $declared[$className][$property] = $type;
        }

        $this->dtoSchema = $declared;

        return $this;
    }

    /**
     * Check the declared schema on every bootstrap, and fail there.
     *
     * For local development: it moves the report from a command you have to
     * remember to run to the first thing that boots. Leave it off in
     * production, where the deploy gate has already answered the question.
     */

    public function validateConfigSchemaOnBoot(bool $enabled = true): self
    {
        $this->shouldValidateConfigSchemaOnBoot = $enabled;

        return $this;
    }

    /**
     * Where `stubs:publish` writes the scaffolder's templates, and where
     * `make:module`/`make:file` look for them before falling back to the
     * built-in ones.
     *
     * Relative to the application root unless an absolute path is given.
     * Defaults to `stubs/gacela`.
     */
    public function setStubsDir(string $dir): self
    {
        $this->stubsDir = $dir;

        return $this;
    }

    /**
     * Replace the dispatcher entirely -- to route Gacela's events onto the bus
     * the application already has, or to answer `hasListeners()` yourself so
     * the framework skips allocating the events you do not want.
     *
     * Either interface is accepted:
     *
     * - Gacela's `EventDispatcherInterface`, which also answers `hasListeners()`
     *   and so decides for itself which events are worth allocating;
     * - any `Psr\EventDispatcher\EventDispatcherInterface` -- Symfony's,
     *   Laravel's, whatever a host framework installed -- which is wrapped in
     *   {@see \Gacela\Framework\Event\Dispatcher\PsrEventDispatcherAdapter}.
     *   PSR-14 cannot be asked what it listens to, so that adapter answers
     *   `hasListeners()` with true and every dispatch site allocates its event;
     *   the price is paid only by an application that installed a bus.
     *
     * `SetupGacela::setEventDispatcher()` has always accepted one; this is the
     * way to reach it from `Gacela::bootstrap()`, whose closure is handed a
     * `GacelaConfig` and nothing else.
     *
     * Takes precedence over {@see disableEventListeners()}: that switch governs
     * the dispatcher Gacela would *build*, and this one it does not build. An
     * application that hands over a dispatcher is the thing deciding what runs,
     * so decline in `hasListeners()` rather than reaching for the switch.
     */
    public function setEventDispatcher(EventDispatcherInterface|PsrEventDispatcherInterface $eventDispatcher): self
    {
        $this->eventDispatcher = PsrEventDispatcherAdapter::wrap($eventDispatcher);

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
     * The listener runs for that event class and for every event that extends
     * or implements it: `AbstractGacelaClassResolverEvent::class` covers all of
     * the resolver events, and `GacelaEventInterface::class` covers every event
     * there is -- the same reach as {@see registerGenericListener()}, with a
     * callable static analysis can check.
     *
     * The event class is what types the callable, so declaring the listener
     * against the wrong event is an error before it is a listener that never
     * runs.
     *
     * @template T of GacelaEventInterface
     *
     * @param class-string<T> $event
     * @param callable(T):void $listener
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
     * Wrap a service as *one* Provider registers it, leaving every other
     * module that happens to use the same id alone.
     *
     * The pair reads like `tag()` and `Container::tag()` do — one asks
     * *everywhere*, the other asks *there*:
     *
     * - {@see extendService()} wraps an id **wherever it is registered**. Two
     *   Providers reusing an un-namespaced key both get wrapped, which is
     *   right when the id names one app-wide concept and wrong when it does
     *   not.
     * - this one wraps an id **only in the module whose Provider is named**,
     *   which is how one module decorates a sibling's binding without
     *   shadowing the sibling's whole Provider class to change one line.
     *
     * ```php
     * $config->extendProviderService(
     *     CatalogProvider::class,
     *     CatalogProvider::PRICE_FORMATTER,
     *     static fn (PriceFormatter $formatter, Container $container): PriceFormatter
     *         => new RoundingPriceFormatter($formatter),
     * );
     * ```
     *
     * The closure receives the service and the module's container, and may
     * return a replacement or mutate in place — the same contract
     * `Container::extend()` already has. An id the named Provider never
     * registers is reported by `doctor`, where an app-wide extension on a
     * mistyped id is only reported as unmatched anywhere.
     *
     * @param class-string $providerClass
     */
    public function extendProviderService(string $providerClass, string $id, Closure $service): self
    {
        $this->providerServicesToExtend[$providerClass][$id] ??= [];
        $this->providerServicesToExtend[$providerClass][$id][] = $service;

        return $this;
    }

    /**
     * Run a callback on a resolved instance, receiving the instance and the
     * container. Callbacks run in registration order.
     *
     * Fires **once per resolution, not once per instance**. A shared instance
     * fetched three times runs the callback three times, on the same object. So
     * the callback has to be idempotent: setting a property is safe, while
     * appending to a collection, incrementing a counter or registering a
     * listener will repeat. The example below is idempotent for that reason.
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
     * Registered *by id*, and read back with `get($id)` -- from a Provider, or
     * through `getProvidedDependency()`. Autowiring resolves a constructor
     * parameter *by type* against the bindings and never consults this
     * registry, in any container, so a parameter that has to be filled needs
     * {@see addBinding()} or {@see loadDefinitions()} instead. What it does
     * reach is every container the configuration builds, the one that
     * constructs the four pillars included.
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
     * Reachable by id from every container the configuration builds, the one
     * that constructs the four pillars included. Never injectable: what comes
     * back is the closure itself, which is the whole point, and autowiring has
     * no type to match it against -- see {@see addFactory()}.
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
     * Declared on every container the configuration builds, the one that
     * constructs the four pillars included, so `get($alias)` answers the same
     * everywhere. An alias is a second *id*, not a second type: autowiring
     * matches a constructor parameter by type against the bindings and does not
     * look here -- see {@see addFactory()}.
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
     * Registered on every container the configuration builds, the one that
     * constructs the four pillars included, and read back with `get($id)`. Like
     * {@see addFactory()} it is an id rather than a type, so a constructor
     * parameter is not filled from here -- and deferring construction is the
     * point, which resolving it to autowire something would defeat.
     *
     * @param string $id The service identifier
     * @param Closure $factory The factory closure that creates the service when needed
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
     * Applies app-wide, reaching every module's container *and* the container
     * that constructs the four pillars, which is how {@see addBinding()} already
     * behaves -- so an interface a Facade, Factory, Config or Provider asks for
     * in its constructor can be declared here. A module that wants definitions
     * of its own calls `Container::load()` in its Provider, keeping them local
     * to that module's container.
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
     * Declare an extension point: every implementation of one interface, in
     * declaration order, resolvable as a typed
     * {@see \Gacela\Framework\Plugins\PluginStack} through
     * `AbstractFactory::getPluginStack()`.
     *
     * ```php
     * $config->addPluginStack(InvoiceDecorator::class, [
     *     AddVatBreakdownDecorator::class,
     *     AddPaymentTermsDecorator::class,
     * ]);
     * ```
     *
     * Repeated calls append rather than replace, so a later config source --
     * another package's `extendGacelaConfig`, an environment override --
     * contributes to a stack it did not declare, seed first. That is the same
     * rule {@see tag()} follows, and it is why there is no second verb for
     * contributing.
     *
     * Which of the three collections to reach for: a registry answers *the one
     * implementation for this key*, a tag answers *all of these* untyped, and a
     * stack answers *all implementations of this interface*, typed and checked.
     * An entry that does not implement the interface fails when the stack first
     * resolves it, naming the class.
     *
     * @param class-string $contract
     * @param list<class-string> $plugins
     */
    public function addPluginStack(string $contract, array $plugins): self
    {
        $this->pluginStacks[$contract] = [
            ...$this->pluginStacks[$contract] ?? [],
            ...$plugins,
        ];

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
            $this->configDimensions,
            $this->appModulePaths,
            $this->dontDiscover,
            $this->configKeyValues,
            $this->genericListeners,
            $this->specificListeners,
            $this->areEventListenersEnabled,
            $this->gacelaConfigsToExtend,
            $this->plugins,
            $this->servicesToExtend,
            $this->providerServicesToExtend,
            $this->factories,
            $this->protectedServices,
            $this->aliases,
            $this->contextualBindings,
            $this->handlerRegistries,
            $this->pluginStacks,
            $this->lazyServices,
            $this->tags,
            $this->afterResolvingCallbacks,
            $this->definitions,
            $this->configSchema,
            $this->shouldValidateConfigSchemaOnBoot,
            $this->stubsDir,
            $this->dtoSchema,
            $this->eventDispatcher,
        );
    }

}
