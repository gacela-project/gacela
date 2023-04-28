<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Container\ContainerInterface as GacelaContainerInterface;

interface ContainerInterface extends GacelaContainerInterface
{
    public function getLocator(): LocatorInterface;
}
