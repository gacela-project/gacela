<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

/**
 * The one place the two analysers do not agree, pinned so it cannot drift
 * quietly in either direction.
 *
 * A facade whose public method comes from a trait is judged by PHPStan and not
 * by Psalm. That is not a bug in the rule -- both run the same
 * `FacadeOnlyDelegatesAnalyser` -- but in what each host hands a plugin:
 *
 * - PHPStan analyses a trait's methods **once per class that uses it**, so the
 *   rule is handed `fromTheTrait()` with the facade as its class.
 * - Psalm analyses them **once, in the trait's own context**. Its
 *   `AfterFunctionLikeAnalysis` reports the enclosing class as the trait, which
 *   extends nothing, and its `AfterClassLikeAnalysis` hands over the facade's
 *   own AST, where a trait-provided method does not appear.
 *
 * There is no route to a trait method's *body* in a using class's context
 * through Psalm's public plugin API, so this is a limitation to know about
 * rather than a defect to fix. `FacadeOnlyDelegatesRuleTest` pins the PHPStan
 * half.
 */
final class TraitFacadeDivergenceTest extends PsalmFixtureTestCase
{
    public function test_psalm_does_not_judge_a_facade_method_that_comes_from_a_trait(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString(
            'GacelaFacadeOnlyDelegates',
            $errors,
            'precondition: the rule runs at all, so silence below is about the trait and not the rule',
        );

        self::assertSame('', $this->errorsIn('LogicTrait.php'));
        self::assertSame('', $this->errorsIn('TraitUsingFacade.php'));
    }

    protected static function configPath(): string
    {
        return __DIR__ . '/RulesFixture/psalm-rules-fixture.xml';
    }
}
