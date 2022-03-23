<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

final class FileCached implements FileCachedInterface
{
    /** @var array<string,string> */
    private static array $gacelaFileNameCache = [];

    private string $cacheFilename;

    private FileCachedIoInterface $io;

    public function __construct(string $cacheFilename, FileCachedIoInterface $io)
    {
        $this->cacheFilename = $cacheFilename;
        $this->io = $io;
    }

    public static function cleanCache(): void
    {
        self::$gacelaFileNameCache = [];
    }

    public static function dummy(): void
    {
    }

    public function getCachedClassName(string $cacheKey): ?string
    {
        if (empty(self::$gacelaFileNameCache) && $this->io->existsCacheFile($this->cacheFilename)) {
            self::$gacelaFileNameCache = $this->io->readCacheFile($this->cacheFilename);
        }

        return self::$gacelaFileNameCache[$cacheKey] ?? null;
    }

    public function cacheClassName(string $cacheKey, ?string $className): void
    {
        if (null === $className) {
            return;
        }

        if ($this->io->existsCacheFile($this->cacheFilename)) {
            $currentContent = $this->io->readCacheFile($this->cacheFilename);
            $updatedContent = array_merge($currentContent, [
                $cacheKey => $className,
            ]);
        } else {
            $updatedContent = [
                $cacheKey => $className,
            ];
        }

        $this->io->writeCachedData($this->cacheFilename, $updatedContent);
        self::$gacelaFileNameCache = $updatedContent;
    }
}
