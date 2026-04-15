<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Container\Container as GacelaContainer;
use Gacela\Framework\Bootstrap\ContainerConfigurationInterface;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Plugins\LazyHandlerRegistry;

/**
 * This is a decorator class to simplify the usage of the decoupled Container
 */
final class Container extends GacelaContainer implements ContainerInterface
{
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

    /**
     * @param array<class-string,class-string|object|callable> $bindings
     */
    private static function withContainerConfiguration(
        ContainerConfigurationInterface $containerConfig,
        array $bindings,
    ): self {
        $container = new self(
            $bindings,
            $containerConfig->getServicesToExtend(),
        );

        foreach ($containerConfig->getFactories() as $id => $factory) {
            $container->set($id, $container->factory($factory));
        }

        foreach ($containerConfig->getProtectedServices() as $id => $service) {
            $container->set($id, $container->protect($service));
        }

        foreach ($containerConfig->getAliases() as $alias => $id) {
            $container->alias($alias, $id);
        }

        foreach ($containerConfig->getContextualBindings() as $concrete => $needs) {
            foreach ($needs as $abstract => $implementation) {
                /** @var class-string $concrete */
                /** @var class-string $abstract */
                $container->when($concrete)->needs($abstract)->give($implementation);
            }
        }

        foreach ($containerConfig->getHandlerRegistries() as $registryKey => $handlers) {
            $container->set(
                $registryKey,
                static fn (): LazyHandlerRegistry => new LazyHandlerRegistry($handlers, $container),
            );
        }

        return $container;
    }
}
