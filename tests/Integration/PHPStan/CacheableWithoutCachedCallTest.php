<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use Override;

use function explode;
use function implode;
use function str_contains;

/**
 * Runs PHPStan for real over the three shapes a `#[Cacheable]` method can have.
 *
 * The attribute is metadata: `cached()` reads it, and a method that never calls
 * `cached()` is simply not cached. Nothing said so -- not at runtime, where the
 * method silently recomputes, and not in a review, where the attribute sits
 * directly above the body claiming otherwise.
 */
final class CacheableWithoutCachedCallTest extends PhpStanFixtureTestCase
{
    public function test_an_attributed_method_that_never_calls_cached_is_reported(): void
    {
        $errors = $this->analyseFixture();

        self::assertStringContainsString('::forgotTheWrapper() carries #[Cacheable]', $errors);
        self::assertStringContainsString('never calls $this->cached()', $errors);
    }

    public function test_the_finding_says_why_the_attribute_alone_does_nothing(): void
    {
        self::assertStringContainsString(
            'the attribute is metadata that `cached()` reads',
            $this->analyseFixture(),
        );
    }

    public function test_wrapping_the_body_is_left_alone(): void
    {
        self::assertStringNotContainsString('::wrapped()', $this->findingsOfThisRule());
    }

    /**
     * The shape this rule judges the whole class for. `cached()` may live in a
     * private helper -- the documentation describes that, and passing `$method`
     * and `$args` explicitly is what makes it work -- so the attributed method
     * itself contains no `cached()` call and must still be silent.
     */
    public function test_delegating_to_a_helper_that_caches_is_left_alone(): void
    {
        self::assertStringNotContainsString('::delegated()', $this->findingsOfThisRule());
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/CacheableCallFixture/phpstan-cacheable-call.neon';
    }

    /**
     * Only this rule's findings. Asserting against the whole run would conflate
     * rules: `delegated()` calls a private helper, which is what the documented
     * pattern requires and what `gacela.facadeOnlyDelegates` reports -- so a
     * bare "is not mentioned" assertion would fail for a reason that has
     * nothing to do with caching.
     */
    private function findingsOfThisRule(): string
    {
        $lines = [];

        foreach (explode("\n", $this->analyseFixture()) as $line) {
            if (str_contains($line, 'gacela.cacheableWithoutCachedCall')) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }
}
