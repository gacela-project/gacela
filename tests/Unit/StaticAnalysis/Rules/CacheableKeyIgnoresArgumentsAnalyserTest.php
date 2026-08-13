<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules;

use Gacela\Framework\AbstractFacade;
use Gacela\StaticAnalysis\Rules\CacheableKeyIgnoresArgumentsAnalyser;
use Gacela\StaticAnalysis\Violation;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * The key decides what a `#[Cacheable]` entry is filed under, so one with no
 * `{N}` placeholder is the same string for every call: `getUser(2)` is answered
 * with user 1's row. Nothing fails; the wrong record is simply served.
 */
final class CacheableKeyIgnoresArgumentsAnalyserTest extends TestCase
{
    public function test_a_key_without_a_placeholder_on_a_method_with_arguments_is_reported(): void
    {
        $violations = $this->analyse("#[Cacheable(ttl: 60, key: 'user')]", 'int $id');

        self::assertCount(1, $violations);
        // Both halves: what is wrong, then what it costs.
        self::assertStringStartsWith('The #[Cacheable] key', $violations[0]->message);
        self::assertStringContainsString('does not mention the arguments', $violations[0]->message);
        self::assertStringContainsString('the first result is served to all of them', $violations[0]->message);
    }

    public function test_the_finding_names_the_key_and_the_method(): void
    {
        $violations = $this->analyse("#[Cacheable(ttl: 60, key: 'user')]", 'int $id');

        self::assertStringContainsString('"user"', $violations[0]->message);
        self::assertStringContainsString('doThing', $violations[0]->message);
    }

    /**
     * Both ways out, because either is a correct fix and a reader who has just
     * been told the key is wrong should not have to guess which.
     */
    public function test_the_correction_offers_a_placeholder_and_dropping_the_key(): void
    {
        $violations = $this->analyse("#[Cacheable(ttl: 60, key: 'user')]", 'int $id');

        self::assertStringContainsString('"user:{0}"', (string)$violations[0]->tip);
        self::assertStringContainsString('drop `key`', (string)$violations[0]->tip);
    }

    public function test_a_key_carrying_a_placeholder_is_left_alone(): void
    {
        self::assertSame([], $this->analyse("#[Cacheable(ttl: 60, key: 'user:{0}')]", 'int $id'));
    }

    /**
     * `{5}` on a one-argument method interpolates to the empty string --
     * `$args[$index] ?? ''` -- so the key is the same constant for every call
     * and the first result is served to all of them. Exactly the fault this
     * rule exists for, and it passed because the key does carry a `{`.
     */
    public function test_a_placeholder_past_the_last_argument_is_reported(): void
    {
        $violations = $this->analyse("#[Cacheable(ttl: 60, key: 'user:{5}')]", 'int $id');

        self::assertCount(1, $violations);
        self::assertSame('gacela.cacheableKeyIgnoresArguments', $violations[0]->identifier);
        // The following word matters: asserting only "the 1 argument" also
        // matches "the 1 arguments", so a broken plural would read as correct.
        self::assertStringContainsString('within the 1 argument the method takes', $violations[0]->message);
    }

    /**
     * Indexes are zero-based, so the last argument of a one-argument method is
     * `{0}` and `{1}` is already past it. The off-by-one is the whole point of
     * the check, and only a boundary case can tell `<` from `<=`.
     */
    public function test_the_index_after_the_last_argument_is_out_of_range(): void
    {
        $violations = $this->analyse("#[Cacheable(ttl: 60, key: 'u:{1}')]", 'int $id');

        self::assertCount(1, $violations);
        self::assertStringContainsString('within the 1 argument the method takes', $violations[0]->message);
    }

    public function test_the_last_argument_is_in_range(): void
    {
        self::assertSame([], $this->analyse("#[Cacheable(ttl: 60, key: 'u:{1}')]", 'int $id, string $scope'));
    }

    public function test_the_count_is_pluralised(): void
    {
        $violations = $this->analyse("#[Cacheable(ttl: 60, key: 'u:{7}')]", 'int $id, string $scope');

        self::assertStringContainsString('within the 2 arguments the method takes', $violations[0]->message);
    }

    public function test_the_out_of_range_correction_names_the_placeholders_available(): void
    {
        $violations = $this->analyse("#[Cacheable(ttl: 60, key: 'u:{7}')]", 'int $id, string $scope');

        self::assertSame(
            'Use a placeholder the method has an argument for: {0} to {1}.',
            $violations[0]->tip,
        );
    }

    /**
     * One placeholder in range is enough: the key still tells two calls apart,
     * whatever else it carries.
     */
    public function test_one_placeholder_in_range_is_enough(): void
    {
        self::assertSame([], $this->analyse("#[Cacheable(ttl: 60, key: 'u:{0}-{9}')]", 'int $id'));
    }

    /**
     * A variadic takes as many arguments as the call site passes, so no index
     * can be shown to be out of range from the declaration alone.
     */
    public function test_a_variadic_method_is_not_judged_on_the_index(): void
    {
        self::assertSame([], $this->analyse("#[Cacheable(ttl: 60, key: 'u:{3}')]", 'int ...$ids'));
    }

    /**
     * A brace that is not a placeholder interpolates nothing -- the template is
     * copied through -- so the key is as constant as one with no brace at all.
     */
    public function test_a_brace_that_is_not_a_placeholder_is_reported(): void
    {
        $violations = $this->analyse("#[Cacheable(ttl: 60, key: 'user:{id}')]", 'int $id');

        self::assertCount(1, $violations);
        self::assertStringContainsString('does not mention the arguments', $violations[0]->message);
    }

    /**
     * With no arguments there is nothing for the key to mention, and one entry
     * is the only entry there could be.
     */
    public function test_a_method_without_arguments_is_left_alone(): void
    {
        self::assertSame([], $this->analyse("#[Cacheable(ttl: 60, key: 'everything')]", ''));
    }

    /**
     * With no key the trait derives one from the method *and its arguments*,
     * which is already per-argument -- which is why dropping the key is one of
     * the two corrections offered.
     */
    public function test_a_cacheable_without_a_key_is_left_alone(): void
    {
        self::assertSame([], $this->analyse('#[Cacheable(ttl: 60)]', 'int $id'));
    }

    /**
     * A key built at runtime is not judged: whether it carries a placeholder is
     * not knowable here, and guessing would report a project that is correct.
     */
    public function test_a_key_that_is_not_a_literal_is_not_judged(): void
    {
        self::assertSame([], $this->analyse('#[Cacheable(ttl: 60, key: self::PREFIX)]', 'int $id'));
    }

    public function test_a_method_with_no_attribute_at_all_is_left_alone(): void
    {
        self::assertSame([], $this->analyse('', 'int $id'));
    }

    /**
     * Written either way round: on an imported name, or on a partially
     * qualified one.
     */
    public function test_a_partially_qualified_attribute_name_is_recognised(): void
    {
        self::assertCount(1, $this->analyse("#[Attribute\\Cacheable(key: 'user')]", 'int $id'));
    }

    /**
     * The unrelated attribute comes first on purpose: skipping it must not
     * abandon the attributes after it, and with `#[Cacheable]` first the skip's
     * `continue` and `break` are indistinguishable.
     *
     * Written `#[A, B]` rather than as two groups, because the skip being
     * tested walks the attributes *within* one group -- two groups leave the
     * inner loop with a single entry each and prove nothing.
     */
    public function test_an_earlier_unrelated_attribute_in_the_same_group_does_not_end_the_search(): void
    {
        self::assertCount(1, $this->analyse("#[SomethingElse, Cacheable(key: 'user')]", 'int $id'));
    }

    /**
     * And across groups, which is the way it is usually written.
     */
    public function test_an_earlier_unrelated_attribute_group_does_not_end_the_search(): void
    {
        self::assertCount(1, $this->analyse("#[SomethingElse]\n    #[Cacheable(key: 'user')]", 'int $id'));
    }

    /**
     * `NotCacheable` ends in the word without being it, and an unrelated
     * attribute shares nothing but the shape -- neither is this attribute, and
     * a suffix match alone would claim both.
     */
    public function test_an_attribute_that_merely_ends_in_the_word_is_not_this_one(): void
    {
        self::assertSame([], $this->analyse("#[NotCacheable(key: 'user')]", 'int $id'));
    }

    public function test_an_unrelated_attribute_is_not_judged(): void
    {
        self::assertSame([], $this->analyse("#[SomethingElse(key: 'user')]", 'int $id'));
    }

    /**
     * @return list<Violation>
     */
    private function analyse(string $attribute, string $params): array
    {
        $source = sprintf(
            "<?php\nfinal class CheckoutFacade\n{\n    %s\n    public function doThing(%s) {}\n}",
            $attribute,
            $params,
        );

        $analyser = new CacheableKeyIgnoresArgumentsAnalyser();
        $class = new FakeAnalysedClass('App\Checkout\CheckoutFacade', [AbstractFacade::class]);

        return $analyser->analyse(ParseSource::methodIn($source, 'doThing'), $class);
    }
}
