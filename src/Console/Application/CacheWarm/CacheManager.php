<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\ClassResolver\ClassResolverCache;
use Gacela\Framework\Config\Config;

use function array_filter;
use function array_map;
use function array_merge;
use function file_exists;
use function filesize;
use function glob;
use function substr;

final class CacheManager
{
    private const CACHE_FILENAMES = [
        ClassNamePhpCache::FILENAME,
        CustomServicesPhpCache::FILENAME,
    ];

    public function clearCache(): void
    {
        foreach ($this->existingCacheFiles() as $cacheFile) {
            FileCache::delete($cacheFile);
        }
    }

    /**
     * Absolute path of the primary warm cache (the class-resolution cache
     * written by cache:warm) -- the current bootstrap's file, fingerprint
     * included, or the report would name a path nothing writes (#681).
     */
    public function getCacheFilePath(): string
    {
        return AbstractPhpFileCache::absoluteFilename(
            $this->cacheDir(),
            ClassNamePhpCache::FILENAME,
            Config::getInstance()->getAppRootDir(),
            ClassResolverCache::bootstrapFingerprint(),
        );
    }

    public function cacheFileExists(): bool
    {
        return file_exists($this->getCacheFilePath());
    }

    public function getCacheFileSize(): int
    {
        $cacheFile = $this->getCacheFilePath();

        if (!file_exists($cacheFile)) {
            return 0;
        }

        return (int) filesize($cacheFile);
    }

    public function getFormattedCacheFileSize(): string
    {
        return BytesFormatter::format($this->getCacheFileSize());
    }

    /**
     * Every managed cache file that currently exists, mapped to its human-readable size.
     * Captured before clearCache() removes them so callers can report what was cleared.
     *
     * @return array<string, string>
     */
    public function getExistingCacheFilesWithSize(): array
    {
        $result = [];
        foreach ($this->existingCacheFiles() as $cacheFile) {
            $result[$cacheFile] = BytesFormatter::format((int) filesize($cacheFile));
        }

        return $result;
    }

    /**
     * @return array<string>
     */
    private function existingCacheFiles(): array
    {
        return array_filter(
            $this->allCacheFilePaths(),
            file_exists(...),
        );
    }

    /**
     * @return list<string>
     */
    private function allCacheFilePaths(): array
    {
        $cacheDir = $this->cacheDir();
        $appRoot = Config::getInstance()->getAppRootDir();

        // Every spelling: the app-scoped name `put()` writes, the unscoped one
        // written before the caches were scoped, and every bootstrap
        // fingerprint of the class-name cache (#681) -- `cache:clear` that
        // left one entrypoint's file behind would leave the stale answers it
        // holds reachable.
        return array_merge(
            array_map(
                static fn (string $filename): string => AbstractPhpFileCache::absoluteFilename($cacheDir, $filename, $appRoot),
                self::CACHE_FILENAMES,
            ),
            array_map(
                static fn (string $filename): string => $cacheDir . DIRECTORY_SEPARATOR . $filename,
                self::CACHE_FILENAMES,
            ),
            $this->fingerprintedClassNameFiles($cacheDir, $appRoot),
        );
    }

    /**
     * @return list<string>
     */
    private function fingerprintedClassNameFiles(string $cacheDir, string $appRoot): array
    {
        if ($appRoot === '') {
            return [];
        }

        $appScoped = AbstractPhpFileCache::absoluteFilename($cacheDir, ClassNamePhpCache::FILENAME, $appRoot);

        // The app-scoped name minus `.php`, plus one fingerprint segment.
        return glob(substr($appScoped, 0, -4) . '-*.php') ?: [];
    }

    private function cacheDir(): string
    {
        return Config::getInstance()->getCacheDir();
    }
}
