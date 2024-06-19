<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

interface CacheInterface
{
    public function has(string $cacheKey): bool;

    public function get(string $cacheKey): string;

    /**
     * @return array<string, string>
     */
    public function getAll(): array;

    public function put(string $cacheKey, string $className): void;
}
