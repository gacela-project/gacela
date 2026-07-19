<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ModuleExample;

use Gacela\Framework\Gacela;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;

/**
 * Warm facade access through a conventional on-disk module (Facade, Factory,
 * Config, Provider classes discovered by naming convention). Complements
 * GacelaGlobalBench, which covers the same flow for anonymous classes
 * registered via Gacela::addGlobal().
 *
 * Sampling: inherits the phpbench.json defaults (200 revs, 10 iterations,
 * warmup) — see tests/Benchmark/README.md.
 */
#[BeforeMethods('setUp')]
#[Groups(['gate', 'resolve'])]
final class ModuleExampleBench
{
    private ModuleExampleFacade $facade;

    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);

        $this->facade = new ModuleExampleFacade();
    }

    public function bench_class_resolving(): void
    {
        $this->facade->getConfigValues();
        $this->facade->getValueFromAbstractProvider();
    }
}
