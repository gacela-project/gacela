<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ClassResolver\ClassInfo;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ClassResolver\ClassInfo;
use GacelaTest\Fixtures\ClassInfoTestingFacade;

final class ClassInfoBench
{
    public function bench_anonymous_class(): void
    {
        $facade = new class() extends AbstractFacade {
        };
        ClassInfo::from($facade, 'Factory');
    }

    public function bench_real_class(): void
    {
        $facade = new ClassInfoTestingFacade();
        ClassInfo::from($facade, 'Factory');
    }
}
