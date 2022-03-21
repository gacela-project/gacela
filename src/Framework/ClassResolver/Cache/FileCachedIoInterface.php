<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

interface FileCachedIoInterface
{
    public function existsCacheFile(string $filename): bool;

    /**
     * @return array<string,string>
     */
    public function readCacheFile(string $filename): array;

    /**
     * @param array<string,string> $data
     */
    public function writeCachedData(string $filename, array $data): void;
}
