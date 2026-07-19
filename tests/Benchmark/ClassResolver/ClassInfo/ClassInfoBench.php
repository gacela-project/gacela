<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ClassResolver\ClassInfo;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ClassResolver\ClassInfo;
use GacelaTest\Fixtures\ClassInfoTestingFacade;
use PhpBench\Attributes\Assert;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

/**
 * Measures ClassInfo::from() alone: the caller objects are constructed once in
 * setUp so the subjects don't mix allocation cost into the measurement.
 *
 * Informational, not gated: these subjects run at sub-microsecond scale, where
 * timer quantization moves the mode by ~10% between runs, so a strict +/-10%
 * baseline assert false-fails unrelated PRs. The widened tolerance keeps the
 * numbers reported/compared without letting noise break CI. See #458.
 */
#[Assert('mode(variant.time.avg) <= mode(baseline.time.avg) +/- 1000%')]
#[BeforeMethods('setUp')]
#[Groups(['micro', 'resolve'])]
#[Revs(1000)]
#[Iterations(5)]
final class ClassInfoBench
{
    private AbstractFacade $anonymousFacade;

    private ClassInfoTestingFacade $realFacade;

    public function setUp(): void
    {
        $this->anonymousFacade = new class() extends AbstractFacade {
        };
        $this->realFacade = new ClassInfoTestingFacade();
    }

    public function bench_anonymous_class(): void
    {
        ClassInfo::from($this->anonymousFacade, 'Factory');
    }

    public function bench_real_class(): void
    {
        ClassInfo::from($this->realFacade, 'Factory');
    }
}
