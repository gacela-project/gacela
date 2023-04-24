<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Container\Container as GacelaContainer;
use Gacela\Framework\Config\Config;

/**
 * This is a proxy class to simplify the usage of the decoupled Container
 */
final class Container
{
    private function __construct(
        private GacelaContainer $gacelaContainer,
    ) {
    }

    public function get(string $id): mixed
    {
        return $this->gacelaContainer->get($id);
    }

    public function set(string $id, mixed $instance): void
    {
        $this->gacelaContainer->set($id, $instance);
    }

    public static function withConfig(Config $config): self
    {
        return new self(
            new GacelaContainer(
                $config->getFactory()->createGacelaFileConfig()->getMappingInterfaces(),
                $config->getSetupGacela()->getServicesToExtend(),
            ),
        );
    }

    public function getLocator(): Locator
    {
        return Locator::getInstance();
    }
}
