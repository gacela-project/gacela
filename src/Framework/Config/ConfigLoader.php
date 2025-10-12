<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

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

            // Cache the normalized local path to avoid redundant calls
            $normalizedLocalPath = $this->pathNormalizer->normalizePathLocal($configItem);

            foreach ($patterns as $pattern) {
                $matchingPattern = $this->pathFinder->matchingPattern($pattern);
                $configPaths = array_diff($matchingPattern, [$normalizedLocalPath]);

                foreach ($configPaths as $absolutePath) {
                    if (!isset($cacheConfigFileContent[$absolutePath])) {
                        $cacheConfigFileContent[$absolutePath] = $configItem->reader()->read($absolutePath);
                    }

                    $configs[] = $cacheConfigFileContent[$absolutePath];
                }
            }

            // Read local config file inline to avoid redundant iteration
            $configs[] = $configItem->reader()->read($normalizedLocalPath);
        }

        return array_merge(...$configs);
    }
}
