<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\PathNormalizer\AbsolutePathNormalizer;
use Gacela\Framework\Config\PathNormalizer\NoEnvAbsolutePathStrategy;
use Gacela\Framework\Config\PathNormalizer\SuffixAbsolutePathStrategy;

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

    public function createConfigLoader(): ConfigLoader
    {
        return new ConfigLoader(
            $this->createGacelaConfigFileFactory(),
            $this->createPathFinder(),
            $this->createPathNormalizer(),
        );
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

    private function createPathFinder(): PathFinderInterface
    {
        return new PathFinder();
    }

    private function createConfigGacelaMapper(): ConfigGacelaMapper
    {
        return new ConfigGacelaMapper();
    }

    private function createPathNormalizer(): PathNormalizerInterface
    {
        return new AbsolutePathNormalizer([
            AbsolutePathNormalizer::PATTERN => new NoEnvAbsolutePathStrategy($this->appRootDir),
            AbsolutePathNormalizer::PATTERN_WITH_ENV => new SuffixAbsolutePathStrategy($this->appRootDir, $this->getEnv()),
            AbsolutePathNormalizer::LOCAL => new NoEnvAbsolutePathStrategy($this->appRootDir),
        ]);
    }

    private function getEnv(): string
    {
        return getenv('APP_ENV') ?: '';
    }
}
