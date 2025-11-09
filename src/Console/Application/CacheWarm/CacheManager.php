<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\Config\Config;

use function file_exists;
use function sprintf;

final class CacheManager
{
    public function clearCache(): void
    {
        $cacheFile = $this->getCacheFilePath();

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    public function getCacheFilePath(): string
    {
        $cacheDir = Config::getInstance()->getCacheDir();
        return $cacheDir . DIRECTORY_SEPARATOR . ClassNamePhpCache::FILENAME;
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
        $bytes = $this->getCacheFileSize();

        if ($bytes < 1024) {
            return sprintf('%d B', $bytes);
        }

        if ($bytes < 1048576) {
            return sprintf('%.2f KB', $bytes / 1024);
        }

        return sprintf('%.2f MB', $bytes / 1048576);
    }
}
