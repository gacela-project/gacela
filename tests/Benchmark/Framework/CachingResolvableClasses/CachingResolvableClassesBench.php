<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\CachingResolvableClasses;

use Gacela\Framework\Gacela;

/**
 * @BeforeMethods("setUp")
 * @Revs(2500)
 * @Iterations(10)
 */
final class CachingResolvableClassesBench
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, [
            'resolvable-class-names-cache-enabled' => true,
        ]);
    }

    public function bench_cache_files(): void
    {
        (new ModuleA\FacadeModuleA())->loadGacelaCacheFile();
    }
}
