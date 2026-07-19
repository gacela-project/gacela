<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ClassResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameNotFoundEvent;
use Gacela\Framework\Gacela;
use GacelaTest\Benchmark\ModuleExample\ModuleExampleFacade;

use function dirname;

/**
 * Warm-resolve throughput of the guarded event dispatch on the
 * class-resolution hot path: every rev is a cache-hit resolve, which is
 * exactly the ResolvedClassCachedEvent dispatch site.
 *
 * @Revs(1000)
 *
 * @Iterations(5)
 */
final class EventDispatchBench
{
    public function setUpWithoutListeners(): void
    {
        Gacela::bootstrap(dirname(__DIR__) . '/ModuleExample', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $this->warmResolverCache();
    }

    public function setUpWithUnrelatedListener(): void
    {
        Gacela::bootstrap(dirname(__DIR__) . '/ModuleExample', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->registerSpecificListener(ClassNameNotFoundEvent::class, static function (): void {});
        });

        $this->warmResolverCache();
    }

    /**
     * @BeforeMethods("setUpWithoutListeners")
     */
    public function bench_warm_resolve_without_listeners(): void
    {
        (new FactoryResolver())->resolve(ModuleExampleFacade::class);
    }

    /**
     * @BeforeMethods("setUpWithUnrelatedListener")
     */
    public function bench_warm_resolve_with_unrelated_specific_listener(): void
    {
        (new FactoryResolver())->resolve(ModuleExampleFacade::class);
    }

    private function warmResolverCache(): void
    {
        (new FactoryResolver())->resolve(ModuleExampleFacade::class);
    }
}
