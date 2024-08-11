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

use function sprintf;

final class ConfigFactory extends AbstractFactory
{
    private const GACELA_PHP_CONFIG_FILENAME = 'gacela';

    private const GACELA_PHP_CONFIG_EXTENSION = '.php';

    private static ?GacelaConfigFileInterface $gacelaFileConfig = null;

    public function __construct(
        private readonly string $appRootDir,
        private readonly SetupGacelaInterface $setup,
    ) {
    }

    public static function resetCache(): void
    {
        self::$gacelaFileConfig = null;
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
        if (self::$gacelaFileConfig instanceof GacelaConfigFileInterface) {
            return self::$gacelaFileConfig;
        }

        $gacelaConfigFiles = [];
        $fileIo = $this->createFileIo();

        $gacelaPhpDefaultPath = $this->getGacelaPhpDefaultPath();
        if ($fileIo->existsFile($gacelaPhpDefaultPath)) {
            $factoryFromGacelaPhp = new GacelaConfigUsingGacelaPhpFileFactory(
                $gacelaPhpDefaultPath,
                $this->setup,
                $fileIo,
            );
            $gacelaConfigFiles[] = $factoryFromGacelaPhp->createGacelaFileConfig();
        }

        $gacelaPhpPath = $this->getGacelaPhpPathFromEnv();
        if ($fileIo->existsFile($gacelaPhpPath)) {
            $factoryFromGacelaPhp = new GacelaConfigUsingGacelaPhpFileFactory($gacelaPhpPath, $this->setup, $fileIo);
            $gacelaConfigFiles[] = $factoryFromGacelaPhp->createGacelaFileConfig();
        }

        self::$gacelaFileConfig = array_reduce(
            $gacelaConfigFiles,
            static fn (GacelaConfigFileInterface $carry, GacelaConfigFileInterface $item): GacelaConfigFileInterface => $carry->combine($item),
            (new GacelaConfigFromBootstrapFactory($this->setup))->createGacelaFileConfig(),
        );

        return self::$gacelaFileConfig;
    }

    private function createFileIo(): FileIoInterface
    {
        return new FileIo();
    }

    private function getGacelaPhpDefaultPath(): string
    {
        return sprintf(
            '%s/%s%s',
            $this->appRootDir,
            self::GACELA_PHP_CONFIG_FILENAME,
            self::GACELA_PHP_CONFIG_EXTENSION,
        );
    }

    private function getGacelaPhpPathFromEnv(): string
    {
        return sprintf(
            '%s/%s-%s%s',
            $this->appRootDir,
            self::GACELA_PHP_CONFIG_FILENAME,
            $this->env(),
            self::GACELA_PHP_CONFIG_EXTENSION,
        );
    }

    private function env(): string
    {
        return getenv('APP_ENV') ?: '';
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
}
