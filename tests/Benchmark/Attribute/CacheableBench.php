<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Attribute;

use Gacela\Framework\Attribute\Cacheable;
use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Attribute\CacheableTrait;
use Gacela\Framework\Attribute\InMemoryCacheStorage;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

/**
 * Quantifies the cost the default #[Cacheable] path pays for inferring the method
 * name + arguments from the caller's stack frame (debug_backtrace) versus passing
 * them explicitly. Both benches measure a warm cache hit (the repeated hot path).
 *
 * Informational only (not in the `gate` group): the delta is sub-microsecond and
 * well within CI timer noise, so it must not fail the performance regression guard.
 */
#[BeforeMethods('setUp')]
#[Groups(['cacheable'])]
#[Revs(1000)]
#[Iterations(5)]
final class CacheableBench
{
    private CacheableBenchFacade $facade;

    public function setUp(): void
    {
        CacheableConfig::reset();
        CacheableConfig::setStorage(new InMemoryCacheStorage());
        $this->facade = new CacheableBenchFacade();

        // Warm both entries so every measured rev is a cache hit.
        $this->facade->inferredFromBacktrace(7);
        $this->facade->explicitArgs(7);
    }

    public function bench_cache_hit_inferred_via_backtrace(): void
    {
        $this->facade->inferredFromBacktrace(7);
    }

    public function bench_cache_hit_with_explicit_args(): void
    {
        $this->facade->explicitArgs(7);
    }
}

final class CacheableBenchFacade
{
    use CacheableTrait;

    #[Cacheable(ttl: 3600)]
    public function inferredFromBacktrace(int $id): int
    {
        return $this->cached(static fn (): int => $id * 2);
    }

    #[Cacheable(ttl: 3600)]
    public function explicitArgs(int $id): int
    {
        return $this->cached(static fn (): int => $id * 2, __METHOD__, [$id]);
    }
}
