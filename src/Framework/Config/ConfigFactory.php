<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config;

final class ConfigFactory
{
    /** @var array<string, mixed> */
    private array $globalServices;

    /**
     * @param array<string, mixed> $globalServices
     */
    public function __construct(array $globalServices)
    {
        $this->globalServices = $globalServices;
    }

    private const GACELA_PHP_CONFIG_FILENAME = 'gacela.php';

    public function createGacelaConfigFileFactory(): GacelaConfigFileFactoryInterface
    {
        /** @psalm-suppress DeprecatedConstant */
        return new GacelaConfigFileFactory(
            Config::getInstance()->getApplicationRootDir(),
            $this->globalServices,
            self::GACELA_PHP_CONFIG_FILENAME
        );
    }

    public function createPathFinder(): PathFinderInterface
    {
        return new PathFinder();
    }
}
