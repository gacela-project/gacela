<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Container;

use Gacela\Container\Container;
use Gacela\Container\PlanCache;
use GacelaTest\Benchmark\Container\Fixtures\DeepD;
use PhpBench\Attributes\Groups;

/**
 * Gacela's containers are sibling roots -- one per Factory class -- resolving
 * classes they have in common. This is the A/B for sharing one plan cache
 * between them: both subjects do identical work and differ only in whether
 * each container re-reflects the chain.
 *
 * Interleaved in one class on purpose: the absolute numbers drift across
 * machines and sessions, the ratio between two subjects measured back to back
 * does not.
 *
 * Informational, not gated: the isolated subject is a deliberate slow path
 * kept for comparison, so gating it would gate a baseline nobody ships.
 */
#[Groups(['informational', 'container'])]
final class SharedPlanCacheBench
{
    private const MODULE_COUNT = 10;

    /**
     * One plan cache per container: every module reflects DeepD's chain again.
     */
    public function bench_ten_module_containers_isolated_plans(): void
    {
        for ($i = 0; $i < self::MODULE_COUNT; ++$i) {
            $container = new Container([], [], [], new PlanCache());
            $container->get(DeepD::class);
        }
    }

    /**
     * One plan cache for all ten: the first module reflects the chain, the
     * other nine read it.
     */
    public function bench_ten_module_containers_shared_plans(): void
    {
        $plans = new PlanCache();

        for ($i = 0; $i < self::MODULE_COUNT; ++$i) {
            $container = new Container([], [], [], $plans);
            $container->get(DeepD::class);
        }
    }
}
