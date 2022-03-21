<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\CachingResolvableClasses;

use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Gacela;

/**
 * @Revs(50)
 * @Iterations(20)
 */
final class CachingResolvableClassesBench
{
    public function bench_cache_enabled(): void
    {
        Gacela::bootstrap(__DIR__, [
            'suffix-types' => $this->suffixTypesFn(),
            'resolvable-class-names-cache-enabled' => true,
        ]);

        (new ModuleA\FacadeModuleA())->loadGacelaCacheFile();
        (new ModuleA\FacadeModuleA())->loadGacelaCacheFile();
        (new ModuleA\FacadeModuleA())->loadGacelaCacheFile();
    }

    public function bench_cache_disabled(): void
    {
        Gacela::bootstrap(__DIR__, [
            'suffix-types' => $this->suffixTypesFn(),
            'resolvable-class-names-cache-enabled' => false,
        ]);

        (new ModuleA\FacadeModuleA())->loadGacelaCacheFile();
        (new ModuleA\FacadeModuleA())->loadGacelaCacheFile();
        (new ModuleA\FacadeModuleA())->loadGacelaCacheFile();
    }

    private function suffixTypesFn(): callable
    {
        return static function (SuffixTypesBuilder $suffixTypesBuilder): void {
            $suffixTypesBuilder
                ->addFactory('FactoryModuleA')
                ->addConfig('ConfModuleA')
                ->addDependencyProvider('DepProModuleA');
        };
    }
}
