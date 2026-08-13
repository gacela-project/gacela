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
            // The local file is merged last, so it always overrides the
            // default and env values; the read cache guarantees it is read
            // only once even when it also matches a pattern above.
            $allConfigs[] = $this->readConfigWithCache(
                $this->pathNormalizer->normalizePathLocal($configItem),
                $configItem,
            );
        }

        return array_merge(...$allConfigs);
    }

    /**
     * Every file `loadAll()` would read, in the order it reads them.
     *
     * Exposed so `doctor` can compare the merged-config cache against its
     * sources without re-deriving the patterns — a second copy of this logic
     * would drift, and the check would then be answering about different files
     * than the ones actually loaded.
     *
     * @return list<string>
     */
    public function sourceFiles(): array
    {
        $files = [];

        foreach ($this->gacelaConfigFile->getConfigItems() as $configItem) {
            foreach ($this->patternsOf($configItem) as $pattern) {
                foreach ($this->pathFinder->matchingPattern($pattern) as $absolutePath) {
                    $files[] = $absolutePath;
                }
            }

            // Unlike the patterns, the local path is not globbed, so it is only
            // a source when it is actually there.
            $local = $this->pathNormalizer->normalizePathLocal($configItem);
            if (is_file($local)) {
                $files[] = $local;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * The distinct base patterns this project declared.
     *
     * Distinct rather than one per config item: the same path declared twice is
     * one claim about where configuration lives, and counting it twice would
     * report "1 of 2 paths load nothing" for a project that wrote exactly one.
     *
     * @return list<string>
     */
    public function declaredPatterns(): array
    {
        $patterns = [];

        foreach ($this->gacelaConfigFile->getConfigItems() as $configItem) {
            $pattern = $this->pathNormalizer->normalizePathPattern($configItem);

            if ($pattern !== '') {
                $patterns[] = $pattern;
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * The declared config paths that matched no file at all.
     *
     * Only the base pattern of each item, never the environment-and-dimensions
     * chain: `config/app-prod.php` is meant to be absent everywhere it does not
     * apply, so reporting it would fire on every correctly configured project.
     * The base pattern is the one the project wrote out and expects to load,
     * and a typo in it costs nothing at bootstrap -- the values are simply not
     * there, and the first thing to read one fails somewhere else entirely.
     *
     * @return list<string>
     */
    public function patternsMatchingNothing(): array
    {
        $unmatched = [];

        foreach ($this->gacelaConfigFile->getConfigItems() as $configItem) {
            $pattern = $this->pathNormalizer->normalizePathPattern($configItem);

            if ($pattern !== '' && $this->pathFinder->matchingPattern($pattern) === []) {
                $unmatched[] = $pattern;
            }
        }

        return array_values(array_unique($unmatched));
    }

    /**
     * @return list<string>
     */
    private function patternsOf(GacelaConfigItem $configItem): array
    {
        return [
            $this->pathNormalizer->normalizePathPattern($configItem),
            ...$this->pathNormalizer->normalizePathPatternsWithSuffixes($configItem),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function loadConfigsFromPatterns(GacelaConfigItem $configItem): array
    {
        $mergedConfigs = [];

        foreach ($this->patternsOf($configItem) as $pattern) {
            foreach ($this->pathFinder->matchingPattern($pattern) as $absolutePath) {
                $mergedConfigs[] = $this->readConfigWithCache($absolutePath, $configItem);
            }
        }

        return array_merge(...$mergedConfigs);
    }

    /**
     * @return array<string,mixed>
     */
    private function readConfigWithCache(string $absolutePath, GacelaConfigItem $configItem): array
    {
        // Key by reader too: different config items may point to the same
        // path with different readers, which must not share a cache entry.
        $cacheKey = spl_object_id($configItem->reader()) . '|' . $absolutePath;

        if (!isset($this->cachedConfigs[$cacheKey])) {
            $this->cachedConfigs[$cacheKey] = $configItem->reader()->read($absolutePath);
        }

        return $this->cachedConfigs[$cacheKey];
    }
}
