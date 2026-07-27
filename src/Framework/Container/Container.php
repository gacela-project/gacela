<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Closure;
use Gacela\Container\Container as GacelaContainer;
use Gacela\Container\ContainerStats;
use Gacela\Container\ContextualBindingBuilder;
use Gacela\Framework\Bootstrap\ContainerConfigurationInterface;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Event\Container\ServiceResolvedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use Gacela\Framework\Plugins\LazyHandlerRegistry;
use SplObjectStorage;

use function array_keys;
use function array_map;

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
     * @var SplObjectStorage<Closure, null>
     */
    private readonly SplObjectStorage $doNotWrap;

    /** @var array<string, true> */
    private array $resolvedServiceIds = [];

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
        $this->doNotWrap = new SplObjectStorage();
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

        return $service;
    }

    public function getOrFail(string $id): mixed
    {
        return $this->inner->getOrFail($id);
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
        return $this->inner->make($className, $parameters);
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
        $this->doNotWrap->offsetSet($instance);

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
     * @param string|list<string> $ids
     */
    public function tag(string|array $ids, string $tag): void
    {
        $this->inner->tag($ids, $tag);
    }

    /**
     * @return iterable<mixed>
     */
    public function tagged(string $tag): iterable
    {
        return $this->inner->tagged($tag);
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

        $this->doNotWrap->offsetSet($wrapper);

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

        foreach ($containerConfig->getLazyServices() as $id => $lazyFactory) {
            $container->set($id, $container->factory(static fn (): mixed => $lazyFactory($container)));
            self::notifyBindingRegistered($id);
        }

        return $container;
    }

    private static function notifyBindingRegistered(string $id): void
    {
        if (self::shouldDispatch(BindingRegisteredEvent::class)) {
            self::dispatchEvent(new BindingRegisteredEvent($id));
        }
    }
}
