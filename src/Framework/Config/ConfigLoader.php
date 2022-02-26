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

        $result = [];
        foreach ($gacelaFileConfig->getConfigItems() as $configItem) {
            $absolutePatternPath = $this->pathNormalizer->normalizePathPattern($configItem);
            $matchingPattern = $this->pathFinder->matchingPattern($absolutePatternPath);
            $excludePattern = [$this->normalizePathLocal($configItem)];
            $configPaths = array_diff($matchingPattern, $excludePattern);

            foreach ($configPaths as $absolutePath) {
                if (!isset($cacheConfigFileContent[$absolutePath])) {
                    $cacheConfigFileContent[$absolutePath] = $configItem->reader()->read($absolutePath);
                }

                $result[] = $cacheConfigFileContent[$absolutePath];
            }
        }

        foreach ($gacelaFileConfig->getConfigItems() as $configItem) {
            $absolutePatternPathWithEnvironment = $this->pathNormalizer->normalizePathPatternWithEnvironment($configItem);
            $matchingPatternWithEnvironment = $this->pathFinder->matchingPattern($absolutePatternPathWithEnvironment);
            $excludePattern = [$this->normalizePathLocal($configItem)];
            $configPaths = array_diff($matchingPatternWithEnvironment, $excludePattern);

            foreach ($configPaths as $absolutePath) {
                if (!isset($cacheConfigFileContent[$absolutePath])) {
                    $cacheConfigFileContent[$absolutePath] = $configItem->reader()->read($absolutePath);
                }

                $result[] = $cacheConfigFileContent[$absolutePath];
            }
        }

        $configs[] = array_merge(...$result);
        $configs[] = $this->readLocalConfigFile($gacelaFileConfig);

        return array_merge(...$configs);
    }

    /**
     * @return array<string,mixed>
     */
    private function readLocalConfigFile(GacelaConfigFile $gacelaConfigFile): array
    {
        $result = [];
        $configItems = $gacelaConfigFile->getConfigItems();

        foreach ($configItems as $configItem) {
            $absolutePath = $this->normalizePathLocal($configItem);
            $result[] = $configItem->reader()->read($absolutePath);
        }

        return array_merge(...array_filter($result));
    }

    private function normalizePathLocal(GacelaConfigItem $configItem): string
    {
        return $this->pathNormalizer->normalizePathLocal($configItem);
    }
}
