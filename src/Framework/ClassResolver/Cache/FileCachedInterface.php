<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use Gacela\Framework\ClassResolver\ClassInfo;

interface FileCachedInterface
{
    public function getCachedClassName(ClassInfo $classInfo): ?string;

    public function cacheClassName(ClassInfo $classInfo, ?string $className): void;
}
