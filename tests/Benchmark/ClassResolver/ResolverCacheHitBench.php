<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ClassResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\Gacela;
use GacelaTest\Benchmark\ModuleExample\ModuleExampleFacade;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

use function dirname;

/**
 * The hottest steady-state path in the framework: every facade access after
 * warmup goes through AbstractClassResolver::doResolve() and is served from
 * the in-memory instance cache. Each rev is exactly one cache-hit resolve
 * with no listeners registered.
 */
#[BeforeMethods('setUp')]
#[Groups(['gate', 'resolve'])]
#[Revs(1000)]
#[Iterations(5)]
final class ResolverCacheHitBench
{
    public function setUp(): void
    {
        Gacela::bootstrap(dirname(__DIR__) . '/ModuleExample', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        // Warm the resolver cache so every measured rev is a cache hit.
        (new FactoryResolver())->resolve(ModuleExampleFacade::class);
    }

    public function bench_resolver_cache_hit(): void
    {
        (new FactoryResolver())->resolve(ModuleExampleFacade::class);
    }
}
