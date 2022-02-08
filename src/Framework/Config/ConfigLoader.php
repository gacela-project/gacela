<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

final class ConfigLoader
{
    private GacelaConfigFileFactoryInterface $configFactory;

    private PathFinderInterface $pathFinder;

    private PathNormalizerInterface $pathNormalizer;

    public function __construct(
        GacelaConfigFileFactoryInterface $configFactory,
        PathFinderInterface $pathFinder,
        PathNormalizerInterface $configPathGenerator
    ) {
        $this->configFactory = $configFactory;
        $this->pathFinder = $pathFinder;
        $this->pathNormalizer = $configPathGenerator;
    }

    /**
     * @return array<string,mixed>
     */
    public function loadAll(): array
    {
        $gacelaFileConfig = $this->configFactory->createGacelaFileConfig();
        $configs = [];
        $cacheConfigFileContent = [];

        foreach ($this->scanAllPatternConfigFiles($gacelaFileConfig) as $absolutePath) {
            if (!isset($cacheConfigFileContent[$absolutePath])) {
                $fileResult = $this->readConfigFromFile($gacelaFileConfig, $absolutePath);
                $cacheConfigFileContent[$absolutePath] = $fileResult;
            }
            $configs[] = $cacheConfigFileContent[$absolutePath];
        }

        $configs[] = $this->readLocalConfigFile($gacelaFileConfig);

        return array_merge(...$configs);
    }

    /**
     * @return iterable<string>
     */
    private function scanAllPatternConfigFiles(GacelaConfigFile $gacelaFileConfig): iterable
    {
        $configGroup = [];
        $configGroup[] = $this->scanAllConfigFileWithPattern($gacelaFileConfig);
        $configGroup[] = $this->scanAllConfigFileWithEnvPattern($gacelaFileConfig);

        foreach (array_merge(...$configGroup) as $path) {
            yield $path;
        }
    }

    /**
     * @return list<string>
     */
    private function scanAllConfigFileWithPattern(GacelaConfigFile $gacelaFileConfig): array
    {
        $configGroup = [];
        foreach ($gacelaFileConfig->getConfigItems() as $configItem) {
            $absolutePatternPath = $this->normalizePathPattern($configItem);
            $matchingPattern = $this->pathFinder->matchingPattern($absolutePatternPath);
            $excludePattern = [$this->normalizePathLocal($configItem)];

            $configGroup[] = array_diff($matchingPattern, $excludePattern);
        }

        return array_values(array_merge(...$configGroup));
    }

    /**
     * @return list<string>
     */
    private function scanAllConfigFileWithEnvPattern(GacelaConfigFile $gacelaFileConfig): array
    {
        $configGroup = [];
        foreach ($gacelaFileConfig->getConfigItems() as $configItem) {
            $absolutePatternPath = $this->normalizePathPatternWithEnv($configItem);
            $matchingPattern = $this->pathFinder->matchingPattern($absolutePatternPath);
            $excludePattern = [$this->normalizePathLocal($configItem)];

            $configGroup[] = array_diff($matchingPattern, $excludePattern);
        }

        return array_values(array_merge(...$configGroup));
    }

    /**
     * @return array<string,mixed>
     */
    private function readConfigFromFile(GacelaConfigFile $gacelaConfigFile, string $absolutePath): array
    {
        $result = [];
        $configItems = $gacelaConfigFile->getConfigItems();

        foreach ($gacelaConfigFile->getConfigReaders() as $type => $reader) {
            $config = $configItems[$type] ?? null;
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
     * @return array<string,mixed>
     */
    private function readLocalConfigFile(GacelaConfigFile $gacelaConfigFile): array
    {
        $result = [];
        $configItems = $gacelaConfigFile->getConfigItems();

        foreach ($gacelaConfigFile->getConfigReaders() as $type => $reader) {
            $config = $configItems[$type] ?? null;
            if ($config === null) {
                continue;
            }
            $absolutePath = $this->normalizePathLocal($config);

            $result[] = $reader->canRead($absolutePath)
                ? $reader->read($absolutePath)
                : [];
        }

        return array_merge(...array_filter($result));
    }

    private function normalizePathLocal(GacelaConfigItem $configItem): string
    {
        return $this->pathNormalizer->normalizePathLocal($configItem);
    }

    private function normalizePathPattern(GacelaConfigItem $configItem): string
    {
        return $this->pathNormalizer->normalizePathPattern($configItem);
    }

    private function normalizePathPatternWithEnv(GacelaConfigItem $configItem): string
    {
        return $this->pathNormalizer->normalizePathPatternWithEnv($configItem);
    }
}
