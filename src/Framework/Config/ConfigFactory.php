<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config;

final class ConfigFactory
{
    /** @deprecated */
    private const GACELA_JSON_CONFIG_FILENAME = 'gacela.json';

    private const GACELA_PHP_CONFIG_FILENAME = 'gacela.php';

    public function createGacelaConfigFileFactory(): GacelaConfigFileFactoryInterface
    {
        /** @psalm-suppress DeprecatedConstant */
        return new GacelaConfigFileFactory(
            Config::getInstance()->getApplicationRootDir(),
            self::GACELA_PHP_CONFIG_FILENAME,
            self::GACELA_JSON_CONFIG_FILENAME
        );
    }

    public function createPathFinder(): PathFinderInterface
    {
        return new PathFinder();
    }
}
