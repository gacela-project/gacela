<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigFromBootstrapFactory;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigUsingGacelaPhpFileFactory;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Config\PathNormalizer\AbsolutePathNormalizer;
use Gacela\Framework\Config\PathNormalizer\WithoutSuffixAbsolutePathStrategy;
use Gacela\Framework\Config\PathNormalizer\WithSuffixAbsolutePathStrategy;

use function sprintf;

/**
 * Assembles the configuration during bootstrap, before any module exists.
 *
 * Deliberately not an AbstractFactory: that base is for module factories and
 * pulls in the container, the config resolver and the provider resolvers —
 * none of which are available (or needed) this early.
 */
final class ConfigFactory
{
    private const GACELA_PHP_CONFIG_FILENAME = 'gacela';

    private const GACELA_PHP_CONFIG_EXTENSION = '.php';

    private static ?GacelaConfigFileInterface $gacelaFileConfig = null;

    /**
     * What the memo above was built from. The memo is keyed on identity rather
     * than served unconditionally, because a second `Gacela::bootstrap()` in
     * one process builds a new setup and used to be handed the first one's
     * merged config — bindings included — unless the caller opted into
     * `resetInMemoryCache()`. Holding the instance rather than a hash of it
     * keeps the comparison exact and rules out `spl_object_id()` reuse.
     */
    private static ?SetupGacelaInterface $memoizedSetup = null;

    private static ?string $memoizedAppRootDir = null;

    public function __construct(
        private readonly string $appRootDir,
        private readonly SetupGacelaInterface $setup,
    ) {
    }

    public static function resetCache(): void
    {
        self::$gacelaFileConfig = null;
        self::$memoizedSetup = null;
        self::$memoizedAppRootDir = null;
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
        $memoized = $this->memoized();
        if ($memoized instanceof GacelaConfigFileInterface) {
            return $memoized;
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

        return $this->memoize(array_reduce(
            $gacelaConfigFiles,
            static fn (GacelaConfigFileInterface $carry, GacelaConfigFileInterface $item): GacelaConfigFileInterface => $carry->merge($item),
            (new GacelaConfigFromBootstrapFactory($this->setup))->createGacelaFileConfig(),
        ));
    }

    /**
     * The memo, but only when it belongs to this factory's app root and setup.
     */
    private function memoized(): ?GacelaConfigFileInterface
    {
        if (self::$memoizedAppRootDir !== $this->appRootDir || self::$memoizedSetup !== $this->setup) {
            return null;
        }

        return self::$gacelaFileConfig;
    }

    private function memoize(GacelaConfigFileInterface $gacelaFileConfig): GacelaConfigFileInterface
    {
        self::$gacelaFileConfig = $gacelaFileConfig;
        self::$memoizedSetup = $this->setup;
        self::$memoizedAppRootDir = $this->appRootDir;

        return $gacelaFileConfig;
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
        return AppEnv::current();
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
