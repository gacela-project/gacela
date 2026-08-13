<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Closure;
use Gacela\Container\ClassSource;
use Gacela\Container\CompilationReport;
use Gacela\Container\Container as GacelaContainer;
use Gacela\Container\ContainerInterface as GacelaContainerInterface;
use Gacela\Container\ContainerStats;
use Gacela\Container\ContextualBindingBuilder;
use Gacela\Container\DependencyNode;
use Gacela\Container\PlanCache;
use Gacela\Container\ValidationReport;
use Gacela\Framework\Bootstrap\ContainerConfigurationInterface;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Event\Container\ServiceResolvedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use Gacela\Framework\Plugins\LazyHandlerRegistry;
use Gacela\Framework\Plugins\LazyPluginStack;
use Throwable;

use function array_keys;
use function is_object;
use function is_string;

/**
 * Decorates the decoupled Container, adding the Locator and the service
 * lifecycle events.
 *
 * Composition rather than inheritance: `Gacela\Container\Container` is `final`
 * as of 1.0. Every method here forwards to the inner container.
 *
 * Implementing the interface does *not* keep that forwarding honest, which it
 * was once assumed to: 1.x promises never to extend `ContainerInterface`, so
 * every capability added since 1.0 landed on the concrete class, where an
 * unforwarded method compiles fine and is simply unreachable. What keeps this
 * honest is {@see \GacelaTest\Integration\Architecture\ContainerForwardingCoverageTest}.
 *
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 * @psalm-import-type Binding from \Gacela\Container\ContainerInterface
 * @psalm-import-type BindingsMap from \Gacela\Container\ContainerInterface as ContainerBindingsMap
 * @psalm-import-type StatsArray from \Gacela\Container\ContainerInterface
 * @psalm-import-type CompiledPlans from \Gacela\Container\PlanRegistry
 * @psalm-import-type AfterResolvingMap from ContainerConfigurationInterface
 * @psalm-import-type DefinitionSources from ContainerConfigurationInterface
 */
final class Container implements ContainerInterface
{
    use EventDispatchingCapabilities;

    /**
     * Not `readonly`: {@see decorating()} wraps a scope the inner container
     * built, which arrives after this object does.
     */
    private GacelaContainer $inner;

    /** @var array<string, true> */
    private array $resolvedServiceIds = [];

    /**
     * Kept local rather than delegated to the inner container, whose 2.0
     * `afterResolving()` matches instances the same way but fires for *any*
     * resolved value. A resolution hook handed a string cannot wire it,
     * and a typed callback would TypeError -- see the object guard in
     * {@see fireAfterResolving()}. Reconciling the two is a behaviour decision,
     * not part of a dependency bump.
     *
     * Instance state, not static: `Gacela::resetCache()` rebuilds the container,
     * which is what clears these.
     *
     * @var AfterResolvingMap
     */
    private array $afterResolvingHooks = [];

    /**
     * @param ContainerBindingsMap $bindings
     * @param array<string, list<Closure>> $instancesToExtend
     * @param CompiledPlans $compiledPlans
     * @param PlanCache|null $planCache defaults to the process-wide cache every
     *   Gacela container shares; pass one to isolate a container instead
     */
    public function __construct(
        array $bindings = [],
        array $instancesToExtend = [],
        array $compiledPlans = [],
        ?PlanCache $planCache = null,
    ) {
        $this->inner = (new GacelaContainer(
            $bindings,
            $instancesToExtend,
            $compiledPlans,
            $planCache ?? SharedPlanCache::getInstance(),
        ))->withSelfReference($this);
    }

    public static function withConfig(Config $config): self
    {
        return self::withContainerConfiguration(
            $config->getSetupGacela(),
            $config->getFactory()->createGacelaFileConfig()->getBindings(),
        );
    }

    public function getLocator(): LocatorInterface
    {
        return Locator::getInstance($this);
    }

    public function get(string $id): mixed
    {
        /** @var mixed $service */
        $service = $this->inner->get($id);

        // Guard first so a container with no listener pays nothing: no dedup-map
        // growth and no per-get work, keeping get() zero-cost when events are off.
        if (self::shouldDispatch(ServiceResolvedEvent::class) && !isset($this->resolvedServiceIds[$id])) {
            $this->resolvedServiceIds[$id] = true;
            self::dispatchEvent(new ServiceResolvedEvent($id));
        }

        if ($this->afterResolvingHooks !== []) {
            $this->fireAfterResolving($id, $service);
        }

        return $service;
    }

    public function getOrFail(string $id): mixed
    {
        /** @var mixed $service */
        $service = $this->inner->getOrFail($id);

        if ($this->afterResolvingHooks !== []) {
            $this->fireAfterResolving($id, $service);
        }

        return $service;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     * @param array<string, mixed> $parameters
     *
     * @return T
     */
    public function make(string $className, array $parameters = []): object
    {
        $instance = $this->inner->make($className, $parameters);

        if ($this->afterResolvingHooks !== []) {
            $this->fireAfterResolving($className, $instance);
        }

        return $instance;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function resolve(callable $callable, array $parameters = []): mixed
    {
        return $this->inner->resolve($callable, $parameters);
    }

    public function has(string $id): bool
    {
        return $this->inner->has($id);
    }

    /**
     * Whether the container actually owns something for this id -- a binding, a
     * stored instance, or a singleton it has already resolved.
     *
     * Narrower than {@see has()}, which is also true of anything merely
     * autowirable. New in container 1.3 and, like {@see stats()}, declared on
     * the concrete class rather than ContainerInterface, so it is forwarded
     * explicitly here.
     */
    public function provides(string $id): bool
    {
        return $this->inner->provides($id);
    }

    public function afterResolving(string $id, Closure $callback): void
    {
        $this->inner->afterResolving($id, $callback);
    }

    /**
     * @param Binding $concrete
     */
    public function bind(string $abstract, string|callable|object $concrete): void
    {
        $this->inner->bind($abstract, $concrete);
    }

    /**
     * @param Binding|null $concrete
     */
    public function singleton(string $abstract, string|callable|object|null $concrete = null): void
    {
        $this->inner->singleton($abstract, $concrete);
    }

    public function bound(string $id): bool
    {
        return $this->inner->bound($id);
    }

    /**
     * @param Binding $concrete
     */
    public function bindIf(string $abstract, string|callable|object $concrete): void
    {
        $this->inner->bindIf($abstract, $concrete);
    }

    /**
     * @param Binding|null $concrete
     */
    public function singletonIf(string $abstract, string|callable|object|null $concrete = null): void
    {
        $this->inner->singletonIf($abstract, $concrete);
    }

    /**
     * Store anything under an id: a Closure is resolved on first read, and
     * every other value is handed back as it was given.
     *
     * `mixed` on purpose. Psalm infers `Closure` from the call sites it can
     * see and offers to narrow the signature to it -- which would reject
     * `$container->set(Pool::class, new Pool())`, the form the documentation
     * uses. Do not apply that fix.
     */
    public function set(string $id, mixed $instance): void
    {
        $this->inner->set($id, $instance);
    }

    public function remove(string $id): void
    {
        $this->inner->remove($id);
    }

    public function factory(Closure $instance): Closure
    {
        return $this->inner->factory($instance);
    }

    public function extend(string $id, Closure $instance): Closure
    {
        return $this->inner->extend($id, $instance);
    }

    public function protect(Closure $instance): Closure
    {
        return $this->inner->protect($instance);
    }

    /**
     * A child container that resolves everything this one resolves, plus what
     * is registered on it directly. Decorated in turn, so a scope keeps the
     * Locator and the lifecycle events -- `createScope(): static` is typed that
     * way upstream precisely so a decorator's scope is a decorator.
     *
     * This is how a module container is built: {@see \Gacela\Framework\AbstractFactory}
     * takes one scope of the app container per Factory class, which is what
     * makes `gacela.php` a once-per-bootstrap walk rather than a once-per-Factory
     * one. A scope inherits the parent's plan registry, so the reflection a
     * sibling already paid for is not paid again.
     */
    public function createScope(): static
    {
        return $this->decorating($this->inner->createScope());
    }

    /**
     * Prove these classes resolve, without resolving them.
     *
     * @param list<class-string>|ClassSource $classNames
     */
    public function validate(array|ClassSource $classNames): ValidationReport
    {
        return $this->inner->validate($classNames);
    }

    /**
     * Hand $facade to service closures instead of this container.
     *
     * Forwarded for completeness rather than for Gacela's own use: the
     * constructor already points the inner container at this decorator, which
     * is what lets a provider written as `static fn (Container $c) => ...`
     * reach {@see getLocator()}. Calling this again redirects it somewhere else.
     */
    public function withSelfReference(GacelaContainerInterface $facade): self
    {
        $this->inner->withSelfReference($facade);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRegisteredServices(): array
    {
        return $this->inner->getRegisteredServices();
    }

    public function isFactory(string $id): bool
    {
        return $this->inner->isFactory($id);
    }

    public function isFrozen(string $id): bool
    {
        return $this->inner->isFrozen($id);
    }

    /**
     * @return ContainerBindingsMap
     */
    public function getBindings(): array
    {
        return $this->inner->getBindings();
    }

    /**
     * @param list<class-string> $classNames
     */
    public function warmUp(array $classNames): void
    {
        $this->inner->warmUp($classNames);
    }

    public function alias(string $alias, string $id): void
    {
        $this->inner->alias($alias, $id);
    }

    /**
     * A map gives the entries keys, readable with {@see self::taggedByKey()};
     * a list leaves them addressable only through {@see self::tagged()}.
     *
     * @param string|array<array-key, string> $ids
     */
    public function tag(string|array $ids, string $tag): void
    {
        $this->inner->tag($ids, $tag);
    }

    /**
     * Keyed entries come back under their key, unkeyed ones under their position.
     *
     * @return iterable<array-key, mixed>
     */
    public function tagged(string $tag): iterable
    {
        return $this->inner->tagged($tag);
    }

    /**
     * The one service registered under $key in $tag, resolved on its own --
     * a keyed tag is a lookup table, so the rest of the tag is never built.
     * Throws naming the keys that exist when $key is not one of them.
     */
    public function taggedByKey(string $tag, string $key): mixed
    {
        return $this->inner->taggedByKey($tag, $key);
    }

    /**
     * The keys registered under $tag, in insertion order. Unkeyed entries
     * have no key and are not listed.
     *
     * @return list<string>
     */
    public function taggedKeys(string $tag): array
    {
        return $this->inner->taggedKeys($tag);
    }

    /**
     * @param class-string $className
     *
     * @return list<string>
     */
    public function getDependencyTree(string $className): array
    {
        return $this->inner->getDependencyTree($className);
    }

    /**
     * The dependency graph as a tree, where {@see getDependencyTree()} returns
     * the same classes flattened and deduplicated.
     *
     * Flattening removes what a dependency inspector is opened for: how deep
     * something sits, which constructor parameter asked for it, that several
     * parents pull in the same class, and where a cycle closes. A cycle is
     * marked and cut rather than thrown, since inspecting a broken graph is
     * exactly when this gets reached for.
     *
     * @param class-string $className
     */
    public function dependencyGraph(string $className): DependencyNode
    {
        return $this->inner->dependencyGraph($className);
    }

    /**
     * Register a whole definition set from data rather than from a closure --
     * the counterpart of `addBinding`/`addAlias`/`tag` for wiring that is
     * generated, shared between environments, or diffed in review.
     *
     * ```php
     * $container->load([
     *     LoggerInterface::class => FileLogger::class,
     *     'db.dsn' => ['value' => 'pgsql://localhost/app'],
     * ]);
     * ```
     *
     * Every entry ends up calling the registration method it stands for, so a
     * definition behaves exactly like its imperative equivalent. Later keys
     * override earlier ones, which is what makes layering base + overrides
     * work; 'tags' accumulate instead, the way `tag()` does.
     *
     * Returns the ids it registered, which is the only reliable answer to
     * "what did this source register": reading them back off the container
     * afterwards catches `bind()` and `set()` entries and misses the aliases.
     *
     * @param array<array-key, mixed> $definitions
     * @param (callable(string):void)|null $onRegistered called per id, as each is registered
     *
     * @return list<string>
     */
    public function load(array $definitions, ?callable $onRegistered = null): array
    {
        return $this->inner->load($definitions, $onRegistered);
    }

    /**
     * Load definitions from a '.php' file returning an array, or a '.json' file.
     *
     * The path is used as given. Unlike `GacelaConfig::enableFileCache()`, it is
     * not rebased under the application root: a definitions file is referenced
     * from the config that names it, so `__DIR__` is the honest way to write it
     * and a silent rebase would only move where the failure appears.
     *
     * YAML stays out on purpose -- there is no parser in the container, and
     * adding one means a second runtime dependency. Once here, the documented
     * path is `$container->load(Yaml::parseFile('services.yaml'))`.
     *
     * @param (callable(string):void)|null $onRegistered called per id, as each is registered
     *
     * @throws \Gacela\Container\Exception\ContainerException when the file is
     *         missing, unreadable, of an unsupported type, or does not hold an array
     *
     * @return list<string>
     */
    public function loadFile(string $file, ?callable $onRegistered = null): array
    {
        return $this->inner->loadFile($file, $onRegistered);
    }

    /**
     * @param class-string|list<class-string> $concrete
     */
    public function when(string|array $concrete): ContextualBindingBuilder
    {
        return $this->inner->when($concrete);
    }

    /**
     * @param list<class-string> $classNames
     *
     * @return array<class-string, mixed>
     */
    public function compile(array $classNames): array
    {
        return $this->inner->compile($classNames);
    }

    /**
     * @param list<class-string> $classNames
     */
    public function writeCompiledCache(array $classNames, string $file, ?string $buildStamp = null): void
    {
        $this->inner->writeCompiledCache($classNames, $file, $buildStamp);
    }

    /**
     * Generate plain `new` expressions for the classes whose construction is
     * knowable ahead of time, taking the resolver off the path for them.
     *
     * @param list<class-string> $classNames
     *
     * @return list<class-string> the classes that were compiled
     */
    public function writeCompiledFactories(array $classNames, string $file, ?string $buildStamp = null): array
    {
        return $this->inner->writeCompiledFactories($classNames, $file, $buildStamp);
    }

    /**
     * What {@see writeCompiledFactories()} would make of these classes, and why
     * it refuses the ones it refuses. Writes nothing.
     *
     * @param list<class-string> $classNames
     */
    public function compileReport(array $classNames): CompilationReport
    {
        return $this->inner->compileReport($classNames);
    }

    /**
     * @param array<class-string, callable(): object> $factories
     */
    public function useCompiledFactories(array $factories): void
    {
        $this->inner->useCompiledFactories($factories);
    }

    /**
     * Defer construction until the instance is first used, without needing
     * `#[Lazy]` on a class you may not own.
     */
    public function lazy(string $abstract, string|callable|null $concrete = null): void
    {
        $this->inner->lazy($abstract, $concrete);
    }

    /**
     * Superseded by {@see stats()}, whose shape upstream does cover with its
     * backward compatibility promise. Kept for the whole of 1.x because the
     * interface still declares it.
     *
     * @return StatsArray
     */
    public function getStats(): array
    {
        return $this->inner->getStats();
    }

    /**
     * The same counters as {@see getStats()} as a typed object rather than an
     * array. Not on ContainerInterface -- 1.x promises nothing is added to it --
     * so it is forwarded explicitly here.
     */
    public function stats(): ContainerStats
    {
        return $this->inner->stats();
    }

    public function offsetExists(mixed $offset): bool
    {
        /** @var string $offset */
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        /** @var string $offset */
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        /** @var string $offset */
        $this->set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        /** @var string $offset */
        $this->remove($offset);
    }

    /**
     * Wrap an inner container this class did not build -- the scope returned by
     * {@see createScope()}, which arrives already made.
     */
    private function decorating(GacelaContainer $inner): self
    {
        $decorator = new self();
        $decorator->inner = $inner->withSelfReference($decorator);
        $decorator->afterResolvingHooks = $this->afterResolvingHooks;

        return $decorator;
    }

    /**
     * @param BindingsMap $bindings
     */
    private static function withContainerConfiguration(
        ContainerConfigurationInterface $containerConfig,
        array $bindings,
    ): self {
        $container = new self(
            $bindings,
            $containerConfig->getServicesToExtend(),
        );

        foreach (array_keys($bindings) as $id) {
            self::notifyBindingRegistered($id);
        }

        foreach ($containerConfig->getFactories() as $id => $factory) {
            $container->set($id, $container->factory($factory));
            self::notifyBindingRegistered($id);
        }

        foreach ($containerConfig->getProtectedServices() as $id => $service) {
            $container->set($id, $container->protect($service));
            self::notifyBindingRegistered($id);
        }

        foreach ($containerConfig->getAliases() as $alias => $id) {
            $container->alias($alias, $id);
            self::notifyBindingRegistered($alias);
        }

        foreach ($containerConfig->getContextualBindings() as $concrete => $needs) {
            /** @var mixed $implementation */
            foreach ($needs as $abstract => $implementation) {
                /** @var class-string $concrete */
                ContextualBindingRegistrar::register($container, $concrete, $abstract, $implementation);
                self::notifyBindingRegistered($abstract);
            }
        }

        foreach ($containerConfig->getHandlerRegistries() as $registryKey => $handlers) {
            $container->set(
                $registryKey,
                static fn (): LazyHandlerRegistry => new LazyHandlerRegistry($handlers, $container),
            );
        }

        foreach ($containerConfig->getPluginStacks() as $contract => $plugins) {
            $container->set(
                $contract,
                static fn (): LazyPluginStack => new LazyPluginStack($contract, $plugins, $container),
            );
        }

        // After the ids they name are registered, so a tagged binding is already
        // resolvable; before lazy services only because tagging never resolves.
        foreach ($containerConfig->getTags() as $tag => $ids) {
            $container->tag($ids, $tag);
        }

        $container->afterResolvingHooks = $containerConfig->getAfterResolvingCallbacks();

        foreach ($containerConfig->getLazyServices() as $id => $lazyFactory) {
            $container->set($id, $container->factory(static fn (): mixed => $lazyFactory($container)));
            self::notifyBindingRegistered($id);
        }

        // Last, so the data layer wins: a definitions file is loaded to override
        // what the config declares imperatively, and could not do that from any
        // earlier position. Sources apply in declaration order, so the last one
        // wins among themselves too.
        $container->loadDefinitions($containerConfig->getDefinitions());

        return $container;
    }

    /**
     * Apply the declared definition sources, in the order they were declared,
     * announcing each id they register.
     *
     * Definitions used to register silently. Naming what a source registered
     * meant reconstructing it -- a file's contents are not in hand here, and
     * reading the ids back off the container catches `bind()` and `set()`
     * entries but misses the aliases, which live in a third registry -- so an
     * undercount was traded for no count at all. Container 2.0 reports them
     * directly, which closes it: a listener counting registrations now sees
     * definitions and imperative bindings alike.
     *
     * @param DefinitionSources $sources
     */
    private function loadDefinitions(array $sources): void
    {
        if ($sources === []) {
            return;
        }

        // Guarded like every other dispatch site, so a container with no
        // listener does not pay for a per-id callback it will never use.
        $onRegistered = self::shouldDispatch(BindingRegisteredEvent::class)
            ? static fn (string $id): null => self::dispatchEvent(new BindingRegisteredEvent($id))
            : null;

        foreach ($sources as $definitions) {
            if (is_string($definitions)) {
                $this->loadFile($definitions, $onRegistered);
            } else {
                $this->load($definitions, $onRegistered);
            }
        }
    }

    /**
     * Run the hooks matching a freshly resolved instance.
     *
     * The match is `instanceof`, not a lookup on the requested id, because the
     * useful registration is an interface -- "after anything implementing
     * LoggerAwareInterface is built".
     *
     * A hook that throws takes the instance out of the container with it: a
     * service whose hook wiring failed must not be served to the next caller as
     * though it had succeeded.
     */
    private function fireAfterResolving(string $id, mixed $instance): void
    {
        if (!is_object($instance)) {
            return;
        }

        foreach ($this->afterResolvingHooks as $hookId => $callbacks) {
            if ($hookId !== $id && !$instance instanceof $hookId) {
                continue;
            }

            foreach ($callbacks as $callback) {
                try {
                    $callback($instance, $this);
                } catch (Throwable $exception) {
                    $this->remove($id);
                    throw $exception;
                }
            }
        }
    }

    private static function notifyBindingRegistered(string $id): void
    {
        if (self::shouldDispatch(BindingRegisteredEvent::class)) {
            self::dispatchEvent(new BindingRegisteredEvent($id));
        }
    }
}
