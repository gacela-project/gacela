<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config;

final class ConfigFactory
{
    private const GACELA_PHP_CONFIG_FILENAME = 'gacela.php';

    /** @var array<string, mixed> */
    private array $globalServices;

    /**
     * @param array<string, mixed> $globalServices
     */
    public function __construct(array $globalServices)
    {
        $this->globalServices = $globalServices;
    }

    public function createGacelaConfigFileFactory(): GacelaConfigFileFactoryInterface
    {
        return new GacelaConfigFileFactory(
            Config::getInstance()->getApplicationRootDir(),
            self::GACELA_PHP_CONFIG_FILENAME,
            $this->globalServices,
            $this->createConfigGacelaMapper()
        );
    }

    public function createPathFinder(): PathFinderInterface
    {
        return new PathFinder();
    }

    private function createConfigGacelaMapper(): ConfigGacelaMapper
    {
        return new ConfigGacelaMapper();
    }
}
