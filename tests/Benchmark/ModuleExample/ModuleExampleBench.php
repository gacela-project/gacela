<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ModuleExample;

use Gacela\Framework\Gacela;

/**
 * @Revs(50)
 *
 * @Iterations(3)
 *
 * @BeforeMethods("setUp")
 */
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
