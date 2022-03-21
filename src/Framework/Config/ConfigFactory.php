<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigFromBootstrapFactory;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigUsingGacelaPhpFileFactory;
use Gacela\Framework\Config\PathNormalizer\AbsolutePathNormalizer;
use Gacela\Framework\Config\PathNormalizer\WithoutSuffixAbsolutePathStrategy;
use Gacela\Framework\Config\PathNormalizer\WithSuffixAbsolutePathStrategy;
use Gacela\Framework\Shared\FileIo;
use Gacela\Framework\Shared\FileIoInterface;

final class ConfigFactory extends AbstractFactory
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
        $gacelaPhpPath = $this->appRootDir . '/' . self::GACELA_PHP_CONFIG_FILENAME;
        $fileIo = $this->createFileIo();

        if (!$fileIo->existsFile($gacelaPhpPath)) {
            return new GacelaConfigFromBootstrapFactory($this->globalServices);
        }

        return new GacelaConfigUsingGacelaPhpFileFactory($gacelaPhpPath, $this->globalServices, $fileIo);
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
}
