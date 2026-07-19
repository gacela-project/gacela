<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ClassResolver\ClassInfo;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ClassResolver\ClassInfo;
use GacelaTest\Fixtures\ClassInfoTestingFacade;
use PhpBench\Attributes\Assert;

/**
 * Informational, not gated: these subjects run at sub-microsecond scale, where
 * timer quantization moves the mode by ~10% between runs, so a strict +/-10%
 * baseline assert false-fails unrelated PRs. The widened tolerance keeps the
 * numbers reported/compared without letting noise break CI. See #458.
 */
#[Assert('mode(variant.time.avg) <= mode(baseline.time.avg) +/- 1000%')]
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
