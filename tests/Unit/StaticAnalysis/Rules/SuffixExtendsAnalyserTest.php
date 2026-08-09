<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules;

use Gacela\Framework\AbstractFacade;
use Gacela\StaticAnalysis\Rules\SuffixExtendsAnalyser;
use Gacela\StaticAnalysis\Violation;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\TestCase;

final class SuffixExtendsAnalyserTest extends TestCase
{
    public function test_a_facade_extending_the_pillar_is_allowed(): void
    {
        self::assertSame([], $this->analyse('App\Checkout\CheckoutFacade', [AbstractFacade::class]));
    }

    public function test_a_facade_extending_nothing_is_reported(): void
    {
        $violations = $this->analyse('App\Checkout\CheckoutFacade');

        self::assertCount(1, $violations);
        self::assertSame(
            'Class App\Checkout\CheckoutFacade should extend Gacela\Framework\AbstractFacade',
            $violations[0]->message,
        );
        self::assertSame('gacela.suffixExtends', $violations[0]->identifier);
    }

    /**
     * The finding belongs to the class, which is the node the host is already
     * reporting on -- carrying one of its own would move it off the declaration.
     */
    public function test_a_violation_carries_no_node_of_its_own(): void
    {
        self::assertNull($this->analyse('App\Checkout\CheckoutFacade')[0]->node);
    }

    public function test_a_class_without_the_suffix_is_not_checked(): void
    {
        self::assertSame([], $this->analyse('App\Checkout\CheckoutService'));
    }

    /**
     * A namespace segment ending in the suffix is not a class ending in it;
     * matching the qualified name would report every class under `App\Facade\`.
     */
    public function test_only_the_last_segment_is_matched(): void
    {
        self::assertSame([], $this->analyse('App\CheckoutFacade\Service'));
    }

    /**
     * `AbstractFacade` is itself named after the pillar it defines, and cannot
     * extend itself.
     */
    public function test_the_expected_parent_is_exempt_from_its_own_rule(): void
    {
        self::assertSame([], $this->analyse(AbstractFacade::class));
    }

    /**
     * Interfaces, traits and enums cannot extend a class at all, so telling one
     * of them to would be advice it is impossible to take -- the only way out
     * would be a baseline entry. `PaymentFacade` as an interface is a perfectly
     * ordinary thing for a consumer to write.
     */
    public function test_only_a_class_can_be_told_to_extend_a_pillar(): void
    {
        $analyser = new SuffixExtendsAnalyser('Facade', AbstractFacade::class);
        $class = new FakeAnalysedClass('App\Checkout\CheckoutFacade');

        foreach (['interface', 'trait', 'enum'] as $kind) {
            self::assertSame(
                [],
                $analyser->analyse(ParseSource::classIn('<?php ' . $kind . ' CheckoutFacade {}'), $class),
                $kind . ' cannot extend a class, so reporting it would be unfixable',
            );
        }
    }

    /**
     * An abstract class still can extend, so it is still held to the rule.
     */
    public function test_an_abstract_class_is_still_checked(): void
    {
        $analyser = new SuffixExtendsAnalyser('Facade', AbstractFacade::class);
        $class = new FakeAnalysedClass('App\Checkout\CheckoutFacade');

        self::assertCount(
            1,
            $analyser->analyse(ParseSource::classIn('<?php abstract class CheckoutFacade {}'), $class),
        );
    }

    /**
     * The message says what is wrong; the tip says what to do about it, and
     * PHPStan renders it on its own line.
     */
    public function test_the_violation_carries_the_correction(): void
    {
        self::assertSame(
            'Extend Gacela\Framework\AbstractFacade, or rename it so it does not end in Facade.',
            $this->analyse('App\Checkout\CheckoutFacade')[0]->tip,
        );
    }

    /**
     * An anonymous class has no name to carry a suffix, and nothing a consumer
     * could rename if it were reported.
     */
    public function test_an_anonymous_class_is_not_checked(): void
    {
        $node = ParseSource::classIn('<?php $x = new class {};');
        $analyser = new SuffixExtendsAnalyser('Facade', AbstractFacade::class);

        self::assertSame([], $analyser->analyse($node, new FakeAnalysedClass('App\Checkout\CheckoutFacade')));
    }

    /**
     * @param list<string> $parents
     *
     * @return list<Violation>
     */
    private function analyse(string $className, array $parents = []): array
    {
        $node = ParseSource::classIn('<?php final class Whatever {}');
        $analyser = new SuffixExtendsAnalyser('Facade', AbstractFacade::class);

        return $analyser->analyse($node, new FakeAnalysedClass($className, $parents));
    }
}
