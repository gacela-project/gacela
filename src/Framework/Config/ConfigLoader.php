<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

use function dirname;
use function implode;
use function sha1;
use function strpbrk;

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
     * than the ones actually loaded. Both go through {@see filesOf()}, so the
     * environment layers the base pattern matched are excluded from this list
     * exactly when they are excluded from the merge.
     *
     * @return list<string>
     */
    public function sourceFiles(): array
    {
        $files = [];

        foreach ($this->gacelaConfigFile->getConfigItems() as $configItem) {
            foreach ($this->filesOf($configItem) as $absolutePath) {
                $files[] = $absolutePath;
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
     * Everything whose change can change what `loadAll()` returns: each file it
     * reads, and the nearest literal directory of each declared path, so a file
     * added to or removed from a globbed directory, or a local override created
     * later, counts as a change.
     *
     * @return list<string>
     */
    public function watchedPaths(): array
    {
        $paths = $this->sourceFiles();

        foreach ($this->gacelaConfigFile->getConfigItems() as $configItem) {
            $declared = [
                $this->pathNormalizer->normalizePathPattern($configItem),
                ...$this->pathNormalizer->normalizePathPatternsWithSuffixes($configItem),
                $this->pathNormalizer->normalizePathLocal($configItem),
            ];

            foreach ($declared as $pattern) {
                if ($pattern !== '') {
                    $paths[] = $this->nearestLiteralDirectory($pattern);
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * The declared config paths, as one string: a change to what is declared
     * is a change to the merged config even when every file is untouched.
     */
    public function declarationSignature(): string
    {
        return self::declarationSignatureOf($this->gacelaConfigFile->getConfigItems());
    }

    /**
     * Static so a cache hit can ask it without building a loader.
     *
     * @param list<GacelaConfigItem> $configItems
     */
    public static function declarationSignatureOf(array $configItems): string
    {
        $declared = [];

        foreach ($configItems as $configItem) {
            $declared[] = $configItem->path() . "\0" . $configItem->pathLocal() . "\0" . $configItem->reader()::class;
        }

        return sha1(implode("\n", $declared));
    }

    /**
     * The files the base patterns matched and the base layer does not read.
     *
     * `doctor` reports these, and it has to: the rule is about filenames rather
     * than about intent, so a project whose `config/app-extra.php` is not an
     * environment file at all would otherwise have it silently stop loading.
     * Naming every exclusion is what makes the trade a reported non-load instead
     * of a second silent one.
     *
     * @see EnvironmentLayer for the rule and why the declared alphabet cannot answer it
     *
     * @return list<EnvironmentLayer>
     */
    public function excludedEnvironmentLayers(): array
    {
        $layers = [];

        foreach ($this->gacelaConfigFile->getConfigItems() as $configItem) {
            foreach (EnvironmentLayer::within($this->basePatternMatches($configItem)) as $path => $layer) {
                $layers[$path] = $layer;
            }
        }

        return array_values($layers);
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
     * Asked of the base *files* rather than of the glob, so it answers about
     * what the base layer reads after the environment layers are excluded. It
     * cannot start reporting a pattern that used to match: an excluded file is
     * always a layer of a shorter one that was matched too, so at least one file
     * survives whenever the glob found anything at all.
     *
     * @return list<string>
     */
    public function patternsMatchingNothing(): array
    {
        $unmatched = [];

        foreach ($this->gacelaConfigFile->getConfigItems() as $configItem) {
            $pattern = $this->pathNormalizer->normalizePathPattern($configItem);

            if ($pattern !== '' && $this->baseLayerFiles($configItem) === []) {
                $unmatched[] = $pattern;
            }
        }

        return array_values(array_unique($unmatched));
    }

    /**
     * Every file one config item contributes, in the order they are merged.
     *
     * The single answer to "which files" -- `loadAll()`, `sourceFiles()` and the
     * `doctor` checks behind them all read it, so the base layer's exclusion of
     * the environment files cannot apply to one of them and not another.
     *
     * @return list<string>
     */
    private function filesOf(GacelaConfigItem $configItem): array
    {
        $files = $this->baseLayerFiles($configItem);

        foreach ($this->pathNormalizer->normalizePathPatternsWithSuffixes($configItem) as $pattern) {
            foreach ($this->pathFinder->matchingPattern($pattern) as $absolutePath) {
                $files[] = $absolutePath;
            }
        }

        return $files;
    }

    /**
     * What the base pattern matched, less the environment layers among it.
     *
     * @return list<string>
     */
    private function baseLayerFiles(GacelaConfigItem $configItem): array
    {
        // Globbed once and handed to the rule: this runs on the bootstrap path,
        // where the pattern is the one thing every config item has.
        $matches = $this->basePatternMatches($configItem);
        $layers = EnvironmentLayer::within($matches);

        return array_values(array_filter(
            $matches,
            static fn (string $absolutePath): bool => !isset($layers[$absolutePath]),
        ));
    }

    /**
     * Not re-indexed: every caller either filters through `array_values()` or
     * only looks the paths up by name, so the glob's own keys are nobody's
     * business.
     *
     * @return array<array-key,string>
     */
    private function basePatternMatches(GacelaConfigItem $configItem): array
    {
        return $this->pathFinder->matchingPattern(
            $this->pathNormalizer->normalizePathPattern($configItem),
        );
    }

    private function nearestLiteralDirectory(string $pattern): string
    {
        $directory = dirname($pattern);

        while (strpbrk($directory, '*?[{') !== false && dirname($directory) !== $directory) {
            $directory = dirname($directory);
        }

        return $directory;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadConfigsFromPatterns(GacelaConfigItem $configItem): array
    {
        $mergedConfigs = [];

        foreach ($this->filesOf($configItem) as $absolutePath) {
            $mergedConfigs[] = $this->readConfigWithCache($absolutePath, $configItem);
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
