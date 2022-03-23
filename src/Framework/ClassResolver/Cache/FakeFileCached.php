<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

final class FakeFileCached implements FileCachedInterface
{
    public function getCachedClassName(string $cacheKey): ?string
    {
        return null;
    }

    public function cacheClassName(string $cacheKey, ?string $className): void
    {
    }
}
