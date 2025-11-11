<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Container\Container as GacelaContainer;
use Gacela\Framework\Bootstrap\ContainerConfigurationInterface;
use Gacela\Framework\Config\Config;

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

    /**
     * @param array<class-string,class-string|object|callable> $bindings
     */
    public static function withContainerConfiguration(
        ContainerConfigurationInterface $containerConfig,
        array $bindings,
    ): self {
        $container = new self(
            $bindings,
            $containerConfig->getServicesToExtend(),
        );

        // Register factory services
        foreach ($containerConfig->getFactories() as $id => $factory) {
            $container->set($id, $container->factory($factory));
        }

        // Register protected services
        foreach ($containerConfig->getProtectedServices() as $id => $service) {
            $container->set($id, $container->protect($service));
        }

        // Register aliases
        foreach ($containerConfig->getAliases() as $alias => $id) {
            $container->alias($alias, $id);
        }

        // Register contextual bindings
        foreach ($containerConfig->getContextualBindings() as $concrete => $needs) {
            foreach ($needs as $abstract => $implementation) {
                /** @var class-string $concrete */
                /** @var class-string $abstract */
                $container->when($concrete)->needs($abstract)->give($implementation);
            }
        }

        return $container;
    }

    public function getLocator(): LocatorInterface
    {
        return Locator::getInstance($this);
    }
}
