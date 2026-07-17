<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\Config\Config;

use function array_filter;
use function array_map;
use function array_values;
use function file_exists;
use function filesize;

final class CacheManager
{
    private const CACHE_FILENAMES = [
        ClassNamePhpCache::FILENAME,
        CustomServicesPhpCache::FILENAME,
    ];

    public function clearCache(): void
    {
        foreach ($this->existingCacheFiles() as $cacheFile) {
            unlink($cacheFile);
        }
    }

    /**
     * Absolute path of the primary warm cache (the class-resolution cache written by cache:warm).
     */
    public function getCacheFilePath(): string
    {
        return $this->cacheDir() . DIRECTORY_SEPARATOR . ClassNamePhpCache::FILENAME;
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
     * @return list<string>
     */
    private function existingCacheFiles(): array
    {
        return array_values(array_filter(
            $this->allCacheFilePaths(),
            static fn (string $cacheFile): bool => file_exists($cacheFile),
        ));
    }

    /**
     * @return list<string>
     */
    private function allCacheFilePaths(): array
    {
        $cacheDir = $this->cacheDir();

        return array_map(
            static fn (string $filename): string => $cacheDir . DIRECTORY_SEPARATOR . $filename,
            self::CACHE_FILENAMES,
        );
    }

    private function cacheDir(): string
    {
        return Config::getInstance()->getCacheDir();
    }
}
