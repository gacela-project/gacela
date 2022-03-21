<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use Gacela\Framework\ClassResolver\ClassInfo;

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

    public function getCachedClassName(ClassInfo $classInfo): ?string
    {
        if (empty(self::$gacelaFileNameCache) && $this->io->existsCacheFile($this->cacheFilename)) {
            self::$gacelaFileNameCache = $this->io->readCacheFile($this->cacheFilename);
        }

        return self::$gacelaFileNameCache[$classInfo->getCacheKey()] ?? null;
    }

    public function cacheClassName(ClassInfo $classInfo, ?string $className): void
    {
        if (null === $className) {
            return;
        }

        if ($this->io->existsCacheFile($this->cacheFilename)) {
            $currentContent = $this->io->readCacheFile($this->cacheFilename);
            $updatedContent = array_merge($currentContent, [
                $classInfo->getCacheKey() => $className,
            ]);
        } else {
            $updatedContent = [
                $classInfo->getCacheKey() => $className,
            ];
        }

        $this->io->writeCachedData($this->cacheFilename, $updatedContent);
        self::$gacelaFileNameCache = $updatedContent;
    }
}
