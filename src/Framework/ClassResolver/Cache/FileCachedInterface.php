<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

interface FileCachedInterface
{
    public function getCachedClassName(string $cacheKey): ?string;

    public function cacheClassName(string $cacheKey, ?string $className): void;
}
