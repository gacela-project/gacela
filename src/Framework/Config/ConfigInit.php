<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItemInterface;

final class ConfigInit
{
    private string $applicationRootDir;

    private GacelaConfigFileFactoryInterface $configFactory;

    private PathFinderInterface $pathFinder;

    /** @var array<string,ConfigReaderInterface> */
    private array $readers;

    /**
     * @param array<string,ConfigReaderInterface> $readers
     */
    public function __construct(
        string $applicationRootDir,
        GacelaConfigFileFactoryInterface $configFactory,
        PathFinderInterface $pathFinder,
        array $readers
    ) {
        $this->applicationRootDir = $applicationRootDir;
        $this->configFactory = $configFactory;
        $this->pathFinder = $pathFinder;
        $this->readers = $readers;
    }

    public function readAll(): array
    {
        $gacelaFileConfig = $this->configFactory->createGacelaFileConfig();
        $configs = [];

        foreach ($this->scanAllConfigFiles($gacelaFileConfig) as $absolutePath) {
            $configs[] = $this->readConfigFromFile($gacelaFileConfig, $absolutePath);
        }

        $configs[] = $this->readLocalConfigFile($gacelaFileConfig);

        return array_merge(...$configs);
    }

    /**
     * @return string[]
     */
    private function scanAllConfigFiles(GacelaConfigFileInterface $gacelaFileConfig): array
    {
        $configGroup = array_map(
            fn (GacelaConfigItemInterface $config): array => array_map(
                static fn ($p): string => (string)$p,
                array_diff(
                    $this->pathFinder->matchingPattern($this->generateAbsolutePath($config->path())),
                    [$this->generateAbsolutePath($config->pathLocal())]
                )
            ),
            $gacelaFileConfig->configs()
        );

        return array_merge(...array_values($configGroup));
    }

    private function readConfigFromFile(GacelaConfigFileInterface $gacelaJson, string $absolutePath): array
    {
        $result = [];
        $configs = $gacelaJson->configs();

        foreach ($this->readers as $type => $reader) {
            $config = $configs[$type] ?? null;
            if ($config === null) {
                continue;
            }

            $result[] = $reader->canRead($absolutePath)
                ? $reader->read($absolutePath)
                : [];
        }

        return array_merge(...array_filter($result));
    }

    private function readLocalConfigFile(GacelaConfigFileInterface $gacelaJson): array
    {
        $result = [];
        $configs = $gacelaJson->configs();

        foreach ($this->readers as $type => $reader) {
            $config = $configs[$type] ?? null;
            if ($config === null) {
                continue;
            }
            $absolutePath = $this->generateAbsolutePath($config->pathLocal());

            $result[] = $reader->canRead($absolutePath)
                ? $reader->read($absolutePath)
                : [];
        }

        return array_merge(...array_filter($result));
    }

    private function generateAbsolutePath(string $relativePath): string
    {
        return sprintf(
            '%s/%s',
            $this->applicationRootDir,
            $relativePath
        );
    }
}
