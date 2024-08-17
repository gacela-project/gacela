<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\NormalModule;

use Gacela\Framework\Gacela;

/**
 * @Revs(5)
 *
 * @Iterations(2)
 *
 * @BeforeMethods("setUp")
 */
final class NormalModuleBench
{
    private NormalModuleFacade $facade;

    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);

        $this->facade = new NormalModuleFacade();
    }

    public function bench_class_resolving(): void
    {
        $this->facade->getConfigValues();
        $this->facade->getValueFromAbstractProvider();
    }
}
