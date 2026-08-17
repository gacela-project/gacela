<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use Override;

/**
 * Runs PHPStan for real over a Facade holding both shapes the rule separates.
 *
 * `FacadeOnlyDelegatesRule` is registered uncommented in `phpstan-gacela.neon`,
 * so every consumer runs it, and nothing on this side drove it: the analyser
 * had its unit tests and the Psalm front end had
 * {@see \GacelaTest\Integration\Psalm\ArchitectureRulesTest}, while the PHPStan
 * rule was reached by no test at all.
 *
 * What a unit test cannot reach is the adaptation -- `getNodeType()` naming a
 * node PHPStan hands over, `getOriginalNode()` still being the method, and the
 * reflection wrapper answering what the analyser asks. A rule that silently
 * matches nothing analyses clean, which looks exactly like code with nothing
 * wrong with it.
 */
final class FacadeOnlyDelegatesTest extends PhpStanFixtureTestCase
{
    public function test_a_facade_method_holding_logic_is_reported(): void
    {
        $errors = $this->analyseFixture();

        self::assertStringContainsString('DelegationFixtureFacade::inlineLogic()', $errors);
        self::assertStringContainsString('must only delegate', $errors);
    }

    /**
     * The finding names every accessor that would have been fine, which is what
     * a reader acts on. PHPStan's separate "tip" is not asserted: the raw error
     * format this runs under does not carry one, so a test for it would pass or
     * fail on the formatter rather than on the rule.
     */
    public function test_the_finding_names_the_accessors_that_would_be_allowed(): void
    {
        self::assertStringContainsString(
            'must only delegate to getFactory()/getConfig()/getProvider()/getResolvedType()',
            $this->analyseFixture(),
        );
    }

    /**
     * The shape that must stay silent, in the same fixture. A rule firing on a
     * plain delegation is one a project turns off, which costs more than the
     * rule was worth.
     */
    public function test_a_method_that_delegates_is_left_alone(): void
    {
        self::assertStringNotContainsString('delegated()', $this->analyseFixture());
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/DelegationFixture/phpstan-delegation.neon';
    }
}
