<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use Override;

/**
 * Runs PHPStan for real over the same shape
 * {@see \GacelaTest\Integration\Psalm\ArchitectureRulesTest} hands Psalm.
 *
 * Every other rule Gacela ships had both halves of that pair. This one had the
 * analyser's own unit tests and the Psalm front end, and nothing driving the
 * PHPStan rule -- the only rule in the file where the pair was incomplete,
 * while being registered uncommented in `phpstan-gacela.neon`, so every
 * consumer runs it.
 *
 * The half a unit test cannot reach is the adaptation: `getNodeType()` naming
 * a node PHPStan hands over, `getOriginalNode()` still carrying the attribute,
 * and the reflection wrapper answering what the analyser asks. A rule that
 * silently matches nothing analyses clean, which is indistinguishable from code
 * that has nothing wrong with it.
 */
final class CacheableKeyIgnoresArgumentsTest extends PhpStanFixtureTestCase
{
    public function test_a_key_that_never_mentions_the_arguments_is_reported(): void
    {
        $errors = $this->analyseFixture();

        self::assertStringContainsString('The #[Cacheable] key "thing" on', $errors);
        self::assertStringContainsString('CachedFacade::bareKey()', $errors);
    }

    /**
     * The consequence, not just the fact: what makes this worth a rule is that
     * nothing fails -- the wrong record is simply served.
     */
    public function test_the_finding_says_what_goes_wrong(): void
    {
        self::assertStringContainsString(
            'every call shares one entry and the first result is served to all of them',
            $this->analyseFixture(),
        );
    }

    /**
     * The same fixture carries both shapes that must stay silent. A rule that
     * fired on either would be one a project turns off, which costs more than
     * the rule was worth.
     */
    public function test_a_key_that_uses_its_argument_is_left_alone(): void
    {
        self::assertStringNotContainsString('keyedByArgument', $this->analyseFixture());
    }

    public function test_a_method_without_arguments_is_left_alone(): void
    {
        self::assertStringNotContainsString('takesNothing', $this->analyseFixture());
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/CacheableFixture/phpstan-cacheable.neon';
    }
}
