<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use Gacela\Framework\ClassResolver\Profiler\FileProfilerInterface;

final class ProfiledInMemoryCache implements ClassNameCacheInterface
{
    private ClassNameCacheInterface $decoratedCache;
    private FileProfilerInterface $fileProfiler;

    public function __construct(
        ClassNameCacheInterface $decoratedCache,
        FileProfilerInterface $fileProfiler
    ) {
        $this->decoratedCache = $decoratedCache;
        $this->fileProfiler = $fileProfiler;
    }

    public function has(string $cacheKey): bool
    {
        return $this->decoratedCache->has($cacheKey);
    }

    public function get(string $cacheKey): string
    {
        return $this->decoratedCache->get($cacheKey);
    }

    public function getAll(): array
    {
        return $this->decoratedCache->getAll();
    }

    public function put(string $cacheKey, string $className): void
    {
        $this->decoratedCache->put($cacheKey, $className);

        $this->fileProfiler->updateProfiler($this->decoratedCache->getAll());
    }
}
