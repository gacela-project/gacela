<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

use function array_map;
use function array_merge;
use function array_values;
use function count;
use function dirname;
use function max;
use function preg_match;
use function serialize;
use function sha1;
use function str_contains;
use function str_starts_with;
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
     * Layer by layer across every config item, as the precedence is documented:
     * all base files, then each environment and dimension layer, then the
     * local files. Item by item, a second `addAppConfig()` base file overrode
     * the first one's environment and local files.
     *
     * @return array<string,mixed>
     */
    public function loadAll(): array
    {
        $allConfigs = [];
        $configItems = $this->gacelaConfigFile->getConfigItems();
        $layersByItem = array_map($this->layersOf(...), $configItems);

        $layerCount = max([0, ...array_map(count(...), $layersByItem)]);

        for ($layer = 0; $layer < $layerCount; ++$layer) {
            foreach ($configItems as $index => $configItem) {
                foreach ($layersByItem[$index][$layer] ?? [] as $absolutePath) {
                    $allConfigs[] = $this->readConfigWithCache($absolutePath, $configItem);
                }
            }
        }

        // The read cache reads a local file once even when a pattern above
        // also matched it; merged again here, it still overrides them.
        foreach ($configItems as $configItem) {
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
     * reads, the directories each declared pattern globs (so a file added or
     * removed counts), the nearest literal one above them (so a new
     * subdirectory counts), and the local override's directory (so creating it
     * later counts).
     *
     * The environment and dimension layers are not listed apart: they only
     * change the file name, so the base pattern's directories already cover
     * them. Duplicates are harmless, since stamps are keyed by path.
     *
     * @return list<string>
     */
    public function watchedPaths(): array
    {
        $paths = $this->sourceFiles();

        foreach ($this->gacelaConfigFile->getConfigItems() as $configItem) {
            $pattern = $this->pathNormalizer->normalizePathPattern($configItem);
            $paths[] = $this->nearestLiteralDirectory($pattern);

            foreach ($this->pathFinder->matchingPattern(dirname($pattern)) as $directory) {
                $paths[] = $directory;
            }

            // An undeclared local path normalizes to the app root itself, whose
            // directory is the root's parent: nothing to watch.
            if ($configItem->pathLocal() !== '') {
                $paths[] = dirname($this->pathNormalizer->normalizePathLocal($configItem));
            }
        }

        foreach ($this->gacelaConfigFile->getConfigCacheWatchPaths() as $watched) {
            $pattern = $this->watchPattern($watched);

            // A plain path is its own stamp, empty while it is missing, so
            // creating it counts. A glob adds the files it matches and the
            // directory they are in, so a file that matches later counts too.
            // Not the directory of a plain path: that is often the root, which
            // anything writing a cache or a dotfile there would change.
            if (strpbrk($pattern, '*?[{') === false) {
                $paths[] = $pattern;
                continue;
            }

            foreach ($this->pathFinder->matchingPattern($pattern) as $match) {
                $paths[] = $match;
            }

            $paths[] = $this->nearestLiteralDirectory($pattern);
        }

        return $paths;
    }

    /**
     * The declared config paths and watched paths: a change to what is
     * declared is a change to the merged config even when every file is
     * untouched. Static so a cache hit can ask it without building a loader.
     */
    public static function declarationSignatureOf(GacelaConfigFileInterface $gacelaConfigFile): string
    {
        return sha1(serialize([
            array_map(
                static fn (GacelaConfigItem $item): array => [$item->path(), $item->pathLocal(), $item->reader()::class],
                $gacelaConfigFile->getConfigItems(),
            ),
            $gacelaConfigFile->getConfigCacheWatchPaths(),
        ]));
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
        return array_merge(...$this->layersOf($configItem));
    }

    /**
     * The base layer first, then one list per environment or dimension layer.
     *
     * @return non-empty-list<list<string>>
     */
    private function layersOf(GacelaConfigItem $configItem): array
    {
        $layers = [$this->baseLayerFiles($configItem)];

        foreach ($this->pathNormalizer->normalizePathPatternsWithSuffixes($configItem) as $pattern) {
            $layers[] = array_values($this->pathFinder->matchingPattern($pattern));
        }

        return $layers;
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

    /**
     * A relative path is resolved against the root like a config path, glob
     * included. An absolute one is taken as it is, wherever it is: the code
     * that computes config values can live outside the application, in a
     * global Composer install or inside a PHAR (`phar://...`), and its
     * upgrade is the change to watch for.
     */
    private function watchPattern(string $watched): string
    {
        if ($this->isAbsolute($watched)) {
            return $watched;
        }

        return $this->pathNormalizer->normalizePathPattern(new GacelaConfigItem($watched));
    }

    /**
     * A unix root, a windows drive, a UNC share, or a stream wrapper.
     */
    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1
            || str_contains($path, '://');
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
