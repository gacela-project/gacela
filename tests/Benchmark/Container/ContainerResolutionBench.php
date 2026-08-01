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
use PhpBench\Attributes\Assert;
use PhpBench\Attributes\Groups;

/**
 * Cold container resolution: each subject deliberately constructs a fresh
 * Container so it measures first-resolution cost (reflection, attribute
 * lookup, binding lookup) — the container allocation is part of the
 * documented path, not incidental noise.
 *
 * Sampling: inherits the phpbench.json defaults — see tests/Benchmark/README.md.
 *
 * **Informational, not gating.** Every subject here times
 * `gacela-project/container`'s resolution path rather than Gacela's own code,
 * so its floor moves whenever that package does — and the guard compares
 * against the *base branch*, which makes a dependency major an automatic
 * failure no change on this side can answer. Adopting container 2.0 measured
 * +11-28% across these four, reported upstream as
 * [container#181](https://github.com/gacela-project/container/issues/181).
 *
 * They still run and still get reported on every pull request; they are simply
 * not a merge blocker while the number they track belongs to someone else.
 * Move back to `gate` once #181 lands and the floor is stable again.
 *
 * This demotes one measurement, not the guard. Every subject that times
 * Gacela's own paths stays in `gate` -- bootstrap, config init, class
 * resolution, the resolver cache, event dispatch, the file caches -- and on the
 * 2.0 bump each of those moved within +-8%, most within +-4%, with bootstrap
 * ~3% faster. The regression is confined to constructing raw containers in a
 * tight loop, which is what these four do and what Gacela does not.
 */
#[Assert('mode(variant.time.avg) <= mode(baseline.time.avg) +/- 1000%')]
#[Groups(['informational', 'container'])]
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
