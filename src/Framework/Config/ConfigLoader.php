<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

final class ConfigLoader
{
    /** @var array<string,array<string,mixed>> */
    private array $cachedConfigs = [];

    public function __construct(
        private readonly GacelaConfigFileInterface $gacelaConfigFile,
        private readonly PathFinderInterface $pathFinder,
        private readonly PathNormalizerInterface $pathNormalizer,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function loadAll(): array
    {
        $allConfigs = [];

        foreach ($this->gacelaConfigFile->getConfigItems() as $configItem) {
            $allConfigs[] = $this->loadConfigsFromPatterns($configItem);
            $allConfigs[] = $this->loadLocalConfig($configItem);
        }

        return array_merge(...$allConfigs);
    }

    /**
     * @return array<string,mixed>
     */
    private function loadConfigsFromPatterns(GacelaConfigItem $configItem): array
    {
        $patterns = [
            $this->pathNormalizer->normalizePathPattern($configItem),
            $this->pathNormalizer->normalizePathPatternWithEnvironment($configItem),
        ];

        $localPath = $this->pathNormalizer->normalizePathLocal($configItem);
        $mergedConfigs = [];

        foreach ($patterns as $pattern) {
            $configPaths = $this->getMatchingConfigPaths($pattern, $localPath);

            foreach ($configPaths as $absolutePath) {
                $mergedConfigs[] = $this->readConfigWithCache($absolutePath, $configItem);
            }
        }

        return array_merge(...$mergedConfigs);
    }

    /**
     * @return array<string,mixed>
     */
    private function loadLocalConfig(GacelaConfigItem $configItem): array
    {
        $localPath = $this->pathNormalizer->normalizePathLocal($configItem);
        return $configItem->reader()->read($localPath);
    }

    /**
     * @return list<string>
     */
    private function getMatchingConfigPaths(string $pattern, string $excludePath): array
    {
        $matchingPaths = $this->pathFinder->matchingPattern($pattern);
        return array_diff($matchingPaths, [$excludePath]);
    }

    /**
     * @return array<string,mixed>
     */
    private function readConfigWithCache(string $absolutePath, GacelaConfigItem $configItem): array
    {
        if (!isset($this->cachedConfigs[$absolutePath])) {
            $this->cachedConfigs[$absolutePath] = $configItem->reader()->read($absolutePath);
        }

        return $this->cachedConfigs[$absolutePath];
    }
}
