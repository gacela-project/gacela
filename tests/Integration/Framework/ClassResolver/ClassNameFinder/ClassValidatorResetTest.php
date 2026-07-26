<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidator;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use stdClass;

use function class_alias;
use function class_exists;

/**
 * `ClassValidator` memoizes `class_exists()`, including the negative answer.
 * A class that was not loadable when first asked stays "missing" for the life
 * of the process unless something clears that cache — and `Gacela::resetCache()`
 * did not, because `ClassValidator::resetCache()` was only ever called by
 * `ClassValidatorTest`. The reset existed, worked, and was reached by nothing
 * but the test written to exercise it.
 *
 * That bites any process where the set of loadable classes changes after the
 * first resolution: a long-running worker (RoadRunner, Swoole, queue consumers)
 * that re-bootstraps, code generation, or `cache:warm` emitting classes. The
 * module resolves to "not found" and nothing points at the stale answer.
 *
 * Only the negative answers are dropped. There is deliberately no test here for
 * the positive ones surviving: once a class is defined, `class_exists()` never
 * consults the autoloader again, so a test could not tell a kept entry from a
 * cleared one and would pass either way. The property is observable only as
 * cost, and `FileCacheBench::bench_without_cache` is what measures it -- it
 * moved +20.07% when this cleared the whole cache, which is what the gate is
 * for.
 */
final class ClassValidatorResetTest extends TestCase
{
    /**
     * Named for this test only. `class_alias()` is process-global and permanent,
     * so a shared name would leak into whatever else asked about it.
     */
    private const LATE_BOUND_CLASS = 'GacelaTest\Runtime\LateBoundAfterResetPillar';

    public function test_a_class_that_becomes_loadable_is_seen_after_reset_cache(): void
    {
        $validator = new ClassValidator();

        self::assertFalse(
            $validator->isClassNameValid(self::LATE_BOUND_CLASS),
            'precondition: the class must not exist yet, so the negative answer gets memoized',
        );

        class_alias(stdClass::class, self::LATE_BOUND_CLASS);
        self::assertTrue(class_exists(self::LATE_BOUND_CLASS), 'precondition: the class is now loadable');

        Gacela::resetCache();

        self::assertTrue(
            $validator->isClassNameValid(self::LATE_BOUND_CLASS),
            'Gacela::resetCache() must clear the memoized class_exists() answers',
        );
    }

}
