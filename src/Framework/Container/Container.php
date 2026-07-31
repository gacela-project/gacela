<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Closure;
use Gacela\Container\Container as GacelaContainer;
use Gacela\Container\ContainerStats;
use Gacela\Container\ContextualBindingBuilder;
use Gacela\Container\DependencyNode;
use Gacela\Framework\Bootstrap\ContainerConfigurationInterface;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Event\Container\ServiceResolvedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use Gacela\Framework\Plugins\LazyHandlerRegistry;
use Throwable;
use WeakMap;

use function array_keys;
use function array_map;
use function is_object;
use function is_string;

/**
 * Decorates the decoupled Container, adding the Locator and the service
 * lifecycle events.
 *
 * Composition rather than inheritance: `Gacela\Container\Container` is `final`
 * as of 1.0. Every method here forwards to the inner container, and
 * implementing the interface is what keeps that forwarding honest -- a method
 * added upstream fails compilation here instead of silently going missing.
 *
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 * @psalm-import-type Binding from \Gacela\Container\ContainerInterface
 * @psalm-import-type BindingsMap from \Gacela\Container\ContainerInterface as ContainerBindingsMap
 * @psalm-import-type StatsArray from \Gacela\Container\ContainerInterface
 * @psalm-import-type CompiledPlans from \Gacela\Container\DependencyResolver
 * @psalm-import-type AfterResolvingMap from ContainerConfigurationInterface
 * @psalm-import-type DefinitionSources from ContainerConfigurationInterface
 */
final class Container implements ContainerInterface
{
    use EventDispatchingCapabilities;

    private readonly GacelaContainer $inner;

    /**
     * Closures set() must pass through untouched: the wrappers this class
     * produced, and closures handed to protect(). Both are marked by *identity*
     * upstream, so wrapping them again would give set() a different object and
     * silently drop the factory/protected mark.
     *
     * A WeakMap rather than an SplObjectStorage, which holds its keys strongly.
     * Nothing removes a mark -- there is no hook that fires when a binding is
     * overwritten or removed -- so strong keys made this a monotonically growing
     * set: every closure ever handed to set(), factory(), extend() or protect()
     * was retained for the container's lifetime, with everything it closed over,
     * whether or not its binding still existed. Held weakly, a mark lasts
     * exactly as long as the closure it marks is reachable, which is precisely
     * as long as the question "is this one of ours?" can still be asked.
     *
     * @var WeakMap<Closure, true>
     */
    private readonly WeakMap $doNotWrap;

    /** @var array<string, true> */
    private array $resolvedServiceIds = [];

    /**
     * Instance state, not static: `Gacela::resetCache()` rebuilds the container,
     * which is what clears these -- so no reset of its own to keep in step with
     * `ResetCacheCoverageTest`.
     *
     * @var AfterResolvingMap
     */
    private array $afterResolvingHooks = [];

    /**
     * @param ContainerBindingsMap $bindings
     * @param array<string, list<Closure>> $instancesToExtend
     * @param CompiledPlans $compiledPlans
     */
    public function __construct(
        array $bindings = [],
        array $instancesToExtend = [],
        array $compiledPlans = [],
    ) {
        $this->inner = new GacelaContainer($bindings, $instancesToExtend, $compiledPlans);
        /** @var WeakMap<Closure, true> $doNotWrap */
        $doNotWrap = new WeakMap();
        $this->doNotWrap = $doNotWrap;
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
        /** @var Binding $decorated */
        $decorated = $this->decorateIfUserClosure($concrete);
        $this->inner->bind($abstract, $decorated);
    }

    /**
     * @param Binding|null $concrete
     */
    public function singleton(string $abstract, string|callable|object|null $concrete = null): void
    {
        /** @var Binding|null $decorated */
        $decorated = $this->decorateIfUserClosure($concrete);
        $this->inner->singleton($abstract, $decorated);
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
        /** @var Binding $decorated */
        $decorated = $this->decorateIfUserClosure($concrete);
        $this->inner->bindIf($abstract, $decorated);
    }

    /**
     * @param Binding|null $concrete
     */
    public function singletonIf(string $abstract, string|callable|object|null $concrete = null): void
    {
        /** @var Binding|null $decorated */
        $decorated = $this->decorateIfUserClosure($concrete);
        $this->inner->singletonIf($abstract, $decorated);
    }

    public function set(string $id, mixed $instance): void
    {
        $this->inner->set($id, $this->decorateIfUserClosure($instance));
    }

    public function remove(string $id): void
    {
        $this->inner->remove($id);
    }

    public function factory(Closure $instance): Closure
    {
        return $this->inner->factory($this->withDecorator($instance));
    }

    public function extend(string $id, Closure $instance): Closure
    {
        return $this->inner->extend($id, $this->withDecorator($instance));
    }

    /**
     * Deliberately not wrapped: a protected closure is never invoked by the
     * container, it is handed back verbatim. There is no container argument to
     * substitute, and wrapping would break the identity callers rely on.
     */
    public function protect(Closure $instance): Closure
    {
        $this->doNotWrap->offsetSet($instance, true);

        return $this->inner->protect($instance);
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
     * Like {@see provides()} and {@see stats()}, declared on the concrete
     * container upstream rather than on ContainerInterface -- 1.x promises
     * nothing is added there -- so it is forwarded explicitly here.
     *
     * @param array<array-key, mixed> $definitions
     */
    public function load(array $definitions): void
    {
        $this->inner->load($definitions);
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
     * @throws \Gacela\Container\Exception\ContainerException when the file is
     *         missing, unreadable, of an unsupported type, or does not hold an array
     */
    public function loadFile(string $file): void
    {
        $this->inner->loadFile($file);
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
    public function writeCompiledCache(array $classNames, string $file): void
    {
        $this->inner->writeCompiledCache($classNames, $file);
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
     * Service closures are invoked by the inner container, which passes
     * *itself*. Under inheritance that used to be this object; under
     * composition it is not, and the documented provider signature
     * `static fn (Container $c) => $c->getLocator()->get(...)` would break --
     * `getLocator()` exists only here.
     *
     * So every user closure is wrapped to substitute this decorator wherever
     * the inner container is passed. Argument-position agnostic, because
     * set(), factory(), protect() and extend() all call with different shapes.
     */
    private function withDecorator(Closure $closure): Closure
    {
        $self = $this;
        $inner = $this->inner;

        $wrapper = static fn (mixed ...$args): mixed => $closure(...array_map(
            static fn (mixed $arg): mixed => ($arg === $inner) ? $self : $arg,
            $args,
        ));

        $this->doNotWrap->offsetSet($wrapper, true);

        return $wrapper;
    }

    private function decorateIfUserClosure(mixed $instance): mixed
    {
        if ($instance instanceof Closure && !$this->doNotWrap->offsetExists($instance)) {
            return $this->withDecorator($instance);
        }

        return $instance;
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
     * Apply the declared definition sources, in the order they were declared.
     *
     * No `BindingRegisteredEvent` is dispatched, deliberately. The loader is an
     * upstream internal, so the only way to name what a source registered is to
     * reconstruct it: a file's contents are not in hand here, and reading the
     * ids back off the container catches `bind()` and `set()` definitions but
     * not the aliases, which live in a third registry. A listener that counts
     * registrations is better served by nothing than by an undercount it cannot
     * see the shape of. Tracked against the upstream loader growing a way to
     * report what it registered.
     *
     * @param DefinitionSources $sources
     */
    private function loadDefinitions(array $sources): void
    {
        foreach ($sources as $definitions) {
            if (is_string($definitions)) {
                $this->loadFile($definitions);
            } else {
                $this->load($definitions);
            }
        }
    }

    /**
     * Run the hooks matching a freshly resolved instance.
     *
     * The match is `instanceof`, not a lookup on the requested id, because the
     * useful registration is an interface -- "after anything implementing
     * LoggerAwareInterface is built". That is also why this cannot delegate to
     * the inner container's own `afterResolving()`, which keys on the exact id.
     *
     * A hook that throws takes the instance out of the container with it: a
     * service whose post-construction wiring failed must not be served to the
     * next caller as though it had succeeded.
     *
     * Callers check `afterResolvingHooks` before calling, the way the event
     * dispatch above is guarded: resolution is the hottest path there is, and a
     * container with no hooks should not pay even a call for a feature it does
     * not use.
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
