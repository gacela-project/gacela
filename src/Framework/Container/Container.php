<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Container\Container as GacelaContainer;
use Gacela\Framework\Config\Config;

/**
 * This is a decorator class to simplify the usage of the decoupled Container
 */
final class Container extends GacelaContainer implements ContainerInterface
{
    public static function withConfig(Config $config): self
    {
        $container = new self(
            $config->getFactory()->createGacelaFileConfig()->getBindings(),
            $config->getSetupGacela()->getServicesToExtend(),
        );

        // Register factory services
        foreach ($config->getSetupGacela()->getFactories() as $id => $factory) {
            $container->set($id, $container->factory($factory));
        }

        // Register protected services
        foreach ($config->getSetupGacela()->getProtectedServices() as $id => $service) {
            $container->set($id, $container->protect($service));
        }

        // Register aliases
        foreach ($config->getSetupGacela()->getAliases() as $alias => $id) {
            $container->alias($alias, $id);
        }

        // Register contextual bindings
        foreach ($config->getSetupGacela()->getContextualBindings() as $concrete => $needs) {
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
