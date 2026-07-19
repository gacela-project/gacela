<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ClassResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameNotFoundEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use GacelaTest\Benchmark\ModuleExample\ModuleExampleFacade;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

use function dirname;

/**
 * Event-dispatch overhead on the class-resolution hot path: every rev is a
 * cache-hit resolve, which is exactly the ResolvedClassCachedEvent dispatch
 * site. Three states: nothing listens (guard short-circuits, no allocation),
 * an unrelated specific listener (guard still short-circuits for this event),
 * and a generic listener (event allocated and dispatched every resolve).
 * Guards the zero-cost dispatch work from #449/#450.
 */
#[Groups(['gate', 'resolve'])]
#[Revs(1000)]
#[Iterations(5)]
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

    public function setUpWithGenericListener(): void
    {
        Gacela::bootstrap(dirname(__DIR__) . '/ModuleExample', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->registerGenericListener(static function (GacelaEventInterface $event): void {});
        });

        $this->warmResolverCache();
    }

    #[BeforeMethods('setUpWithoutListeners')]
    public function bench_warm_resolve_without_listeners(): void
    {
        (new FactoryResolver())->resolve(ModuleExampleFacade::class);
    }

    #[BeforeMethods('setUpWithUnrelatedListener')]
    public function bench_warm_resolve_with_unrelated_specific_listener(): void
    {
        (new FactoryResolver())->resolve(ModuleExampleFacade::class);
    }

    #[BeforeMethods('setUpWithGenericListener')]
    public function bench_warm_resolve_with_generic_listener(): void
    {
        (new FactoryResolver())->resolve(ModuleExampleFacade::class);
    }

    private function warmResolverCache(): void
    {
        (new FactoryResolver())->resolve(ModuleExampleFacade::class);
    }
}
