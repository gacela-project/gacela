<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

interface ClassNameCacheInterface
{
    public function has(string $cacheKey): bool;

    public function get(string $cacheKey): string;

    public function put(string $cacheKey, string $className): void;
}
