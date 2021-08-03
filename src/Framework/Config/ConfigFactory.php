<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config;

final class ConfigFactory
{
    private const GACELA_CONFIG_FILENAME = 'gacela.json';

    public function createGacelaJsonConfigCreator(): GacelaJsonConfigFactoryInterface
    {
        return new GacelaJsonConfigFactory(
            Config::getInstance()->getApplicationRootDir(),
            self::GACELA_CONFIG_FILENAME
        );
    }

    public function createPathFinder(): PathFinderInterface
    {
        return new PathFinder();
    }
}
