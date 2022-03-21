<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\CachingResolvableClasses;

use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Gacela;

/**
 * @BeforeClassMethods("beforeClassSetUp")
 * @Iterations(25)
 * @Revs(1000)
 */
final class ResolvableClassNamesCacheEnabledBench
{
    private static bool $bootstrapped = false;

    public static function beforeClassSetUp(): void
    {
        $gacelaCacheFilepath = __DIR__ . '/' . AbstractClassResolver::GACELA_CACHE_JSON_FILE;
        if (is_file($gacelaCacheFilepath)) {
            unlink($gacelaCacheFilepath);
        }
    }

    public function bench_cache_enabled(): void
    {
        if (!self::$bootstrapped) {
            $this->gacelaBootstrap();
            self::$bootstrapped = true;
        }

        (new ModuleA\FacadeModuleA())->loadGacelaCacheFile();
        (new ModuleA\FacadeModuleA())->loadGacelaCacheFile();

        (new ModuleB\FacadeModuleB())->loadGacelaCacheFile();
        (new ModuleB\FacadeModuleB())->loadGacelaCacheFile();

        (new ModuleC\Facade())->loadGacelaCacheFile();
        (new ModuleC\Facade())->loadGacelaCacheFile();
    }

    private function gacelaBootstrap(): void
    {
        Gacela::bootstrap(__DIR__, [
            'suffix-types' => static function (SuffixTypesBuilder $suffixTypesBuilder): void {
                $suffixTypesBuilder
                    ->addFactory('FactoryModuleA')
                    ->addFactory('FactoryModuleB')
                    ->addConfig('ConfModuleA')
                    ->addConfig('ConfModuleB')
                    ->addDependencyProvider('DepProModuleA')
                    ->addDependencyProvider('DepProModuleB');
            },
            'resolvable-class-names-cache-enabled' => true,
        ]);
    }
}
