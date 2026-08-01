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
use PhpBench\Attributes\Groups;

/**
 * Cold container resolution: each subject deliberately constructs a fresh
 * Container so it measures first-resolution cost (reflection, attribute
 * lookup, binding lookup) — the container allocation is part of the
 * documented path, not incidental noise.
 *
 * Sampling: inherits the phpbench.json defaults — see tests/Benchmark/README.md.
 *
 * Every subject here times `gacela-project/container`'s resolution path rather
 * than Gacela's own code, so its floor moves whenever that package does — and
 * the guard compares against the *base branch*, which makes a dependency major
 * an automatic failure no change on this side can answer. That is what
 * happened on the container 2.0 bump: these four measured +11-28%, they were
 * demoted to `informational` while the number they track belonged to someone
 * else, and the cause was
 * [container#181](https://github.com/gacela-project/container/issues/181) — a
 * per-class argument builder composed on a class's *first* resolution, which a
 * container built once per revolution pays for and never uses again.
 *
 * Fixed in container 2.0.1 and gating again: against 1.5.0 the four now measure
 * +2.1 to +3.5% (20 paired samples), inside the 10% budget. Expect to demote
 * them again for the next container major, and to restore them the same way.
 */
#[Groups(['gate', 'container'])]
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
