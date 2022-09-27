<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

final class ProfiledInMemoryCache implements ClassNameCacheInterface
{
    private ClassNameCacheInterface $decoradedCache;
    private FileProfilerInterface $fileProfiler;

    public function __construct(
        ClassNameCacheInterface $decoratedCache,
        FileProfilerInterface $fileProfiler
    ) {
        $this->decoradedCache = $decoratedCache;
        $this->fileProfiler = $fileProfiler;
    }

    public function has(string $cacheKey): bool
    {
        return $this->decoradedCache->has($cacheKey);
    }

    public function get(string $cacheKey): string
    {
        return $this->decoradedCache->get($cacheKey);
    }

    public function getAll(): array
    {
        return $this->decoradedCache->getAll();
    }

    public function put(string $cacheKey, string $className): void
    {
        $this->decoradedCache->put($cacheKey, $className);
        $this->fileProfiler->updateFileCache($this->decoradedCache->getAll());
    }
}
