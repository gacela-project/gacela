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
        return new self(
            $config->getFactory()->createGacelaFileConfig()->getBindings(),
            $config->getSetupGacela()->getServicesToExtend(),
        );
    }

    public function getLocator(): LocatorInterface
    {
        return Locator::getInstance($this);
    }
}
