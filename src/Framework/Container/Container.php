<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Container\Container as GacelaContainer;
use Gacela\Framework\Bootstrap\ContainerConfigurationInterface;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Event\Container\ServiceResolvedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use Gacela\Framework\Plugins\LazyHandlerRegistry;

/**
 * This is a decorator class to simplify the usage of the decoupled Container
 *
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 */
final class Container extends GacelaContainer implements ContainerInterface
{
    use EventDispatchingCapabilities;

    /** @var array<string, true> */
    private array $resolvedServiceIds = [];

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
        $service = parent::get($id);

        // Guard first so a container with no listener pays nothing: no dedup-map
        // growth and no per-get work, keeping get() zero-cost when events are off.
        if (self::shouldDispatch(ServiceResolvedEvent::class) && !isset($this->resolvedServiceIds[$id])) {
            $this->resolvedServiceIds[$id] = true;
            self::dispatchEvent(new ServiceResolvedEvent($id));
        }

        return $service;
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

        // Register lazy services - wrapped as factories that instantiate on first access
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
