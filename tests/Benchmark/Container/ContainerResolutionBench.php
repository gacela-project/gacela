<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Container;

use Gacela\Container\Container;
use GacelaTest\Benchmark\Container\Fixtures\BindingConsumer;
use GacelaTest\Benchmark\Container\Fixtures\ConcreteService;
use GacelaTest\Benchmark\Container\Fixtures\DeepD;
use GacelaTest\Benchmark\Container\Fixtures\InjectConsumer;
use GacelaTest\Benchmark\Container\Fixtures\ServiceInterface;
use GacelaTest\Benchmark\Container\Fixtures\SimpleClass;

final class ContainerResolutionBench
{
    /**
     * Resolve a class with no constructor dependencies.
     * Baseline for the container overhead itself.
     */
    public function bench_resolve_no_dependencies(): void
    {
        $container = new Container();
        $container->get(SimpleClass::class);
    }

    /**
     * Resolve a class whose parameter uses #[Inject(Implementation::class)].
     * Measures the reflection attribute lookup + override resolution cost.
     */
    public function bench_resolve_with_inject_attribute(): void
    {
        $container = new Container();
        $container->get(InjectConsumer::class);
    }

    /**
     * Resolve a class whose parameter is an interface, resolved via bindings.
     * Measures the standard binding-lookup path (no #[Inject]).
     */
    public function bench_resolve_with_bindings(): void
    {
        $container = new Container([
            ServiceInterface::class => ConcreteService::class,
        ]);
        $container->get(BindingConsumer::class);
    }

    /**
     * Resolve a 4-level dependency chain: DeepD → DeepC → DeepB → DeepA.
     * Measures recursive resolution and reflection caching.
     */
    public function bench_resolve_deep_chain(): void
    {
        $container = new Container();
        $container->get(DeepD::class);
    }
}
