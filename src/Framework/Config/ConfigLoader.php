<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

final class ConfigLoader
{
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
        $cacheConfigFileContent = [];
        $configs = [];

        foreach ($this->gacelaConfigFile->getConfigItems() as $configItem) {
            $patterns = [
                $this->pathNormalizer->normalizePathPattern($configItem),
                $this->pathNormalizer->normalizePathPatternWithEnvironment($configItem),
            ];

            foreach ($patterns as $pattern) {
                foreach ($this->readAbsolutePatternPath($pattern, $configItem, $cacheConfigFileContent) as $config) {
                    $configs[] = $config;
                }
            }
        }

        $configs[] = $this->readLocalConfigFile();

        return array_merge(...$configs);
    }

    /**
     * @return array<string,mixed>
     */
    private function readLocalConfigFile(): array
    {
        $result = [];
        $configItems = $this->gacelaConfigFile->getConfigItems();

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

    /**
     * @param array<string,array<string,mixed>> $cacheConfigFileContent
     *
     * @return list<array<string,mixed>>
     */
    private function readAbsolutePatternPath(
        string $pattern,
        GacelaConfigItem $configItem,
        array &$cacheConfigFileContent,
    ): array {
        $matchingPattern = $this->pathFinder->matchingPattern($pattern);
        $excludePattern = [$this->normalizePathLocal($configItem)];
        $configPaths = array_diff($matchingPattern, $excludePattern);

        /** @var list<array<string,mixed>> $result */
        $result = [];
        foreach ($configPaths as $absolutePath) {
            if (!isset($cacheConfigFileContent[$absolutePath])) {
                $cacheConfigFileContent[$absolutePath] = $configItem->reader()->read($absolutePath);
            }

            $result[] = $cacheConfigFileContent[$absolutePath];
        }

        return $result;
    }
}
