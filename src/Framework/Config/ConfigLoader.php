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
        $configs = [];
        $cacheConfigFileContent = [];

        /** @var list<array<string,mixed>> $result */
        $result = [];
        foreach ($this->gacelaConfigFile->getConfigItems() as $configItem) {
            $absolutePatternPath = $this->pathNormalizer->normalizePathPattern($configItem);
            $result[] = $this->readAbsolutePatternPath($absolutePatternPath, $configItem, $cacheConfigFileContent);
        }

        foreach ($this->gacelaConfigFile->getConfigItems() as $configItem) {
            $absolutePatternPath = $this->pathNormalizer->normalizePathPatternWithEnvironment($configItem);
            $result[] = $this->readAbsolutePatternPath($absolutePatternPath, $configItem, $cacheConfigFileContent);
        }

        /** @psalm-suppress MixedArgument,NamedArgumentNotAllowed */
        $configs[] = array_merge(...array_merge(...$result)); // @phpstan-ignore-line
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
