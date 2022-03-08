<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\PathNormalizer\AbsolutePathNormalizer;
use Gacela\Framework\Config\PathNormalizer\WithoutSuffixAbsolutePathStrategy;
use Gacela\Framework\Config\PathNormalizer\WithSuffixAbsolutePathStrategy;

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
            $this->createFileIo(),
        );
    }

    private function createPathFinder(): PathFinderInterface
    {
        return new PathFinder();
    }

    private function createFileIo(): FileIoInterface
    {
        return new FileIo();
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
