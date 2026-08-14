<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules;

use Gacela\Framework\AbstractFacade;
use Gacela\StaticAnalysis\Rules\CacheableWithoutCachedCallAnalyser;
use Gacela\StaticAnalysis\Violation;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\TestCase;

use function array_map;
use function sprintf;

final class CacheableWithoutCachedCallAnalyserTest extends TestCase
{
    public function test_a_method_with_no_attribute_is_not_judged(): void
    {
        self::assertSame([], $this->analyse('
            public function plain(int $id): int { return $id; }
        '));
    }

    public function test_an_attributed_method_that_calls_cached_is_allowed(): void
    {
        self::assertSame([], $this->analyse('
            #[Cacheable(ttl: 60)]
            public function wrapped(int $id): int { return $this->cached(fn (): int => $id); }
        '));
    }

    public function test_an_attributed_method_that_never_calls_cached_is_reported(): void
    {
        $violations = $this->analyse('
            #[Cacheable(ttl: 60)]
            public function forgot(int $id): int { return $id * 2; }
        ');

        self::assertCount(1, $violations);
        self::assertSame(
            'App\Catalog\CatalogFacade::forgot() carries #[Cacheable] and never calls $this->cached(),'
            . ' so nothing caches it: the attribute is metadata that `cached()` reads.',
            $violations[0]->message,
        );
        self::assertSame('gacela.cacheableWithoutCachedCall', $violations[0]->identifier);
    }

    /**
     * The documented "opting out of backtrace" shape: `cached()` lives in a
     * helper, which is handed the method and args explicitly. From the method
     * alone the helper is invisible, which is why this rule judges the class.
     */
    public function test_delegating_to_a_helper_that_caches_is_allowed(): void
    {
        self::assertSame([], $this->analyse('
            #[Cacheable(ttl: 60)]
            public function delegated(int $id): int { return $this->lookup("delegated", [$id]); }

            private function lookup(string $method, array $args): int { return $this->cached(fn (): int => 1, $method, $args); }
        '));
    }

    /**
     * Calling a sibling that does *not* cache is not delegation, so the
     * attribute is still inert.
     */
    public function test_calling_a_helper_that_does_not_cache_is_reported(): void
    {
        $violations = $this->analyse('
            #[Cacheable(ttl: 60)]
            public function delegated(int $id): int { return $this->compute($id); }

            private function compute(int $id): int { return $id * 2; }
        ');

        self::assertCount(1, $violations);
    }

    /**
     * The scan does not stop at the first method it skips. Written with the
     * ignorable method *first* on purpose: with the reported one first, turning
     * the loop's `continue` into a `break` would change nothing.
     */
    public function test_a_method_without_the_attribute_does_not_end_the_scan(): void
    {
        $violations = $this->analyse('
            public function plain(int $id): int { return $id; }

            #[Cacheable(ttl: 60)]
            public function forgot(int $id): int { return $id * 2; }
        ');

        self::assertSame(['App\Catalog\CatalogFacade::forgot()'], $this->reportedMethods($violations));
    }

    /**
     * The same for a method that is skipped because it *does* cache: it comes
     * first, so a `break` there would hide the one after it.
     */
    public function test_a_caching_method_does_not_end_the_scan(): void
    {
        $violations = $this->analyse('
            #[Cacheable(ttl: 60)]
            public function wrapped(int $id): int { return $this->cached(fn (): int => $id); }

            #[Cacheable(ttl: 60)]
            public function forgot(int $id): int { return $id * 2; }
        ');

        self::assertSame(['App\Catalog\CatalogFacade::forgot()'], $this->reportedMethods($violations));
    }

    public function test_every_forgotten_method_is_reported(): void
    {
        $violations = $this->analyse('
            #[Cacheable(ttl: 60)]
            public function one(int $id): int { return $id; }

            #[Cacheable(ttl: 60)]
            public function two(int $id): int { return $id; }
        ');

        self::assertSame(
            ['App\Catalog\CatalogFacade::one()', 'App\Catalog\CatalogFacade::two()'],
            $this->reportedMethods($violations),
        );
    }

    /**
     * Written either way round, as the sibling cacheable rule accepts it.
     */
    public function test_a_partially_qualified_attribute_is_recognised(): void
    {
        self::assertCount(1, $this->analyse('
            #[Attribute\Cacheable(ttl: 60)]
            public function forgot(int $id): int { return $id * 2; }
        '));
    }

    /**
     * The name has to *be* the attribute or end at a separator before it.
     * `#[NotCacheable]` ends in the same letters and is a different attribute,
     * so matching on the suffix alone would judge a class over someone else's.
     */
    public function test_an_attribute_merely_ending_in_the_same_letters_is_not_it(): void
    {
        self::assertSame([], $this->analyse('
            #[NotCacheable(ttl: 60)]
            public function forgot(int $id): int { return $id * 2; }
        '));
    }

    /**
     * A call on something other than `$this` is not the framework's `cached()`.
     */
    public function test_cached_called_on_another_object_does_not_count(): void
    {
        self::assertCount(1, $this->analyse('
            #[Cacheable(ttl: 60)]
            public function forgot(int $id): int { return $this->getFactory()->cached(fn (): int => $id); }
        '));
    }

    /**
     * A delegating method does not end the scan either. It comes first, so a
     * `break` where it is skipped would hide the forgotten one after it.
     */
    public function test_a_delegating_method_does_not_end_the_scan(): void
    {
        $violations = $this->analyse('
            #[Cacheable(ttl: 60)]
            public function delegated(int $id): int { return $this->lookup("delegated", [$id]); }

            #[Cacheable(ttl: 60)]
            public function forgot(int $id): int { return $id * 2; }

            private function lookup(string $method, array $args): int { return $this->cached(fn (): int => 1, $method, $args); }
        ');

        self::assertSame(['App\Catalog\CatalogFacade::forgot()'], $this->reportedMethods($violations));
    }

    /**
     * Every caching method counts as a delegation target, not just the first
     * one found. Here the helper that caches is the *second*, so keeping only
     * one would report the method that delegates to it.
     */
    public function test_delegation_finds_a_caching_helper_that_is_not_the_first(): void
    {
        self::assertSame([], $this->analyse('
            #[Cacheable(ttl: 60)]
            public function wrapped(int $id): int { return $this->cached(fn (): int => $id); }

            private function lookup(string $method, array $args): int { return $this->cached(fn (): int => 1, $method, $args); }

            #[Cacheable(ttl: 60)]
            public function delegated(int $id): int { return $this->lookup("delegated", [$id]); }
        '));
    }

    /**
     * A call whose receiver is not a plain variable -- `$this->getFactory()->x()`
     * -- is skipped rather than ending the search, so a `cached()` call after it
     * in the same body is still found.
     */
    public function test_a_chained_call_before_cached_does_not_end_the_search(): void
    {
        self::assertSame([], $this->analyse('
            #[Cacheable(ttl: 60)]
            public function wrapped(int $id): int
            {
                $this->getFactory()->warmUp($id);

                return $this->cached(fn (): int => $id);
            }
        '));
    }

    /**
     * @param list<Violation> $violations
     *
     * @return list<string>
     */
    private function reportedMethods(array $violations): array
    {
        return array_map(
            static function (Violation $violation): string {
                $start = strpos($violation->message, 'App\\');
                $end = strpos($violation->message, ')') + 1;

                return substr($violation->message, (int)$start, $end - (int)$start);
            },
            $violations,
        );
    }

    /**
     * @return list<Violation>
     */
    private function analyse(string $members): array
    {
        $node = ParseSource::classIn(sprintf("<?php\nfinal class CatalogFacade\n{\n%s\n}", $members));

        return (new CacheableWithoutCachedCallAnalyser())->analyse(
            $node,
            new FakeAnalysedClass('App\Catalog\CatalogFacade', [AbstractFacade::class]),
        );
    }
}
