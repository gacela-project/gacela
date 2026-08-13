<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\PackageManifest;

use FilesystemIterator;
use Gacela\Console\Domain\AllAppModules\ExcludedDirectories;
use Gacela\Console\Domain\FileContent\JsonFile;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;

/**
 * Every `composer.json` the repository owns.
 *
 * `vendor` is pruned by the same ExcludedDirectories the module scan uses:
 * an installed package's manifest describes a decision somebody else already
 * made, and reporting on it would be noise nobody here can act on.
 */
final class ComposerPackageFinder
{
    public function __construct(
        private readonly ExcludedDirectories $excludedDirectories = new ExcludedDirectories(),
    ) {
    }

    /**
     * @return list<ComposerPackage>
     */
    public function findIn(string $rootDir): array
    {
        $packages = [];

        foreach ($this->manifestPathsIn($rootDir) as $manifestPath) {
            $decoded = $this->decode($manifestPath);

            if ($decoded === null) {
                continue;
            }

            $package = ComposerPackage::fromDecodedJson($decoded, $manifestPath, dirname($manifestPath));

            if ($package instanceof ComposerPackage) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * @return list<string>
     */
    private function manifestPathsIn(string $rootDir): array
    {
        if (!is_dir($rootDir)) {
            return [];
        }

        $excluded = $this->excludedDirectories;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS),
                static fn (SplFileInfo|string $current, string $key, RecursiveDirectoryIterator $inner): bool => !$inner->hasChildren() || !$excluded->isExcluded($inner->getFilename()),
            ),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $paths = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getFilename() === 'composer.json') {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function decode(string $manifestPath): ?array
    {
        return JsonFile::decode($manifestPath);
    }
}
