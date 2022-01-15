<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

final class ConfigInit
{
    private string $applicationRootDir;

    private GacelaConfigFileFactoryInterface $configFactory;

    private PathFinderInterface $pathFinder;

    /** @var array<string, ConfigReaderInterface> */
    private array $readers;

    /**
     * @param array<string, ConfigReaderInterface> $readers
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

    /**
     * @return array<string, mixed>
     */
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
     * @return array<string>
     */
    private function scanAllConfigFiles(GacelaConfigFile $gacelaFileConfig): array
    {
        $configGroup = array_map(
            fn (GacelaConfigItem $config): array => array_diff(
                $this->pathFinder->matchingPattern($this->generateAbsolutePath($config->path())),
                [$this->generateAbsolutePath($config->pathLocal())]
            ),
            $gacelaFileConfig->getConfigs()
        );

        $groupsValues = array_values($configGroup);

        return array_merge(...$groupsValues);
    }

    /**
     * @return array<string, mixed>
     */
    private function readConfigFromFile(GacelaConfigFile $gacelaConfigFile, string $absolutePath): array
    {
        $result = [];
        $configs = $gacelaConfigFile->getConfigs();

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

    /**
     * @return array<string, mixed>
     */
    private function readLocalConfigFile(GacelaConfigFile $gacelaConfigFile): array
    {
        $result = [];
        $configs = $gacelaConfigFile->getConfigs();

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
