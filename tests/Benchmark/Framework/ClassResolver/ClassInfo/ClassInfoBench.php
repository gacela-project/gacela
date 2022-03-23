<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\ClassInfo;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ClassResolver\ClassInfo;
use GacelaTest\Fixtures\ClassInfoTestingFacade;

/**
 * @Iterations(5)
 * @Revs(1000)
 */
final class ClassInfoBench
{
    public function bench_anonymous_class(): void
    {
        $facade = new class() extends AbstractFacade {
        };
        ClassInfo::fromObject($facade, 'Factory');
    }

    public function bench_real_class(): void
    {
        $facade = new ClassInfoTestingFacade();
        ClassInfo::fromObject($facade, 'Factory');
    }
}
