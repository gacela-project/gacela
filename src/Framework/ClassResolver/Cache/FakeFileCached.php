<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use Gacela\Framework\ClassResolver\ClassInfo;

final class FakeFileCached implements FileCachedInterface
{
    public function getCachedClassName(ClassInfo $classInfo): ?string
    {
        return null;
    }

    public function cacheClassName(ClassInfo $classInfo, ?string $className): void
    {
    }
}
