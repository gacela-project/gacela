<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigFromBootstrapFactory;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigUsingGacelaPhpFileFactory;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Config\PathNormalizer\AbsolutePathNormalizer;
use Gacela\Framework\Config\PathNormalizer\WithoutSuffixAbsolutePathStrategy;
use Gacela\Framework\Config\PathNormalizer\WithSuffixAbsolutePathStrategy;

final class ConfigFactory extends AbstractFactory
{
    private const GACELA_PHP_CONFIG_FILENAME = 'gacela';
    private const GACELA_PHP_CONFIG_EXTENSION = '.php';

    private string $appRootDir;

    private SetupGacelaInterface $setup;

    public function __construct(string $appRootDir, SetupGacelaInterface $setup)
    {
        $this->appRootDir = $appRootDir;
        $this->setup = $setup;
    }

    public function createConfigLoader(): ConfigLoader
    {
        return new ConfigLoader(
            $this->createGacelaFileConfig(),
            $this->createPathFinder(),
            $this->createPathNormalizer(),
        );
    }

    public function createGacelaFileConfig(): GacelaConfigFileInterface
    {
        $gacelaPhpPath = $this->getGacelaPhpPath();
        $fileIo = $this->createFileIo();

        if ($fileIo->existsFile($gacelaPhpPath)) {
            $factoryFromGacelaPhp = new GacelaConfigUsingGacelaPhpFileFactory($gacelaPhpPath, $this->setup, $fileIo);
            $gacelaSetupFromGacelaPhp = $factoryFromGacelaPhp->createGacelaFileConfig();
        }

        $factoryFromBootstrap = new GacelaConfigFromBootstrapFactory($this->setup);
        $gacelaSetupFromBootstrap = $factoryFromBootstrap->createGacelaFileConfig();

        if (isset($gacelaSetupFromGacelaPhp) && $gacelaSetupFromGacelaPhp instanceof GacelaConfigFileInterface) {
            return $gacelaSetupFromBootstrap->combine($gacelaSetupFromGacelaPhp);
        }

        return $gacelaSetupFromBootstrap;
    }

    private function createFileIo(): FileIoInterface
    {
        return new FileIo();
    }

    private function createPathFinder(): PathFinderInterface
    {
        return new PathFinder();
    }

    private function createPathNormalizer(): PathNormalizerInterface
    {
        return new AbsolutePathNormalizer([
            AbsolutePathNormalizer::WITHOUT_SUFFIX => new WithoutSuffixAbsolutePathStrategy($this->appRootDir),
            AbsolutePathNormalizer::WITH_SUFFIX => new WithSuffixAbsolutePathStrategy($this->appRootDir, $this->env()),
        ]);
    }

    private function env(): string
    {
        return getenv('APP_ENV') ?: '';
    }

    private function getGacelaPhpPath(): string
    {
        $gacelaPhpPathFromEnv = $this->getGacelaPhpPathFromEnv();
        if ($this->createFileIo()->existsFile($gacelaPhpPathFromEnv)) {
            return $gacelaPhpPathFromEnv;
        }

        return $this->getGacelaPhpDefaultPath();
    }

    private function getGacelaPhpPathFromEnv(): string
    {
        if ($this->env() === '') {
            return $this->getGacelaPhpDefaultPath();
        }

        return sprintf(
            '%s/%s-%s%s',
            $this->appRootDir,
            self::GACELA_PHP_CONFIG_FILENAME,
            $this->env(),
            self::GACELA_PHP_CONFIG_EXTENSION
        );
    }

    private function getGacelaPhpDefaultPath(): string
    {
        return sprintf(
            '%s/%s%s',
            $this->appRootDir,
            self::GACELA_PHP_CONFIG_FILENAME,
            self::GACELA_PHP_CONFIG_EXTENSION
        );
    }
}
