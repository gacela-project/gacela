<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

final class ConfigFactory
{
    private const GACELA_PHP_CONFIG_FILENAME = 'gacela.php';

    private string $appRootDir;

    /** @var array<string,mixed> */
    private array $globalServices;

    /**
     * @param array<string,mixed> $globalServices
     */
    public function __construct(string $appRootDir, array $globalServices)
    {
        $this->appRootDir = $appRootDir;
        $this->globalServices = $globalServices;
    }

    public function createGacelaConfigFileFactory(): GacelaConfigFileFactoryInterface
    {
        return new GacelaConfigFileFactory(
            $this->appRootDir,
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

    public function getEnvironment(): string
    {
        return getenv('APPLICATION_ENV') ?: '';
    }
}
