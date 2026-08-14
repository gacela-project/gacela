<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules;

use Gacela\Framework\AbstractFacade;
use Gacela\StaticAnalysis\Rules\FacadeInterfaceInSyncAnalyser;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\TestCase;

final class FacadeInterfaceInSyncAnalyserTest extends TestCase
{
    /**
     * The skipped methods come first on purpose: skipping one has to continue
     * the scan, not end it, or a single private method near the top of a facade
     * hides every drifted method behind it.
     *
     * The static method sits *between* two instance methods for the same
     * reason: it is skipped, and skipping must not abandon the methods after
     * it. With it last, `continue` and `break` are indistinguishable.
     *
     * Kept as a string rather than a fixture file because cs-fixer reorders a
     * real class by visibility, which would undo exactly that ordering.
     */
    private const SOURCE = <<<'PHP'
        <?php
        final class CheckoutFacade implements CheckoutFacadeInterface
        {
            public function __construct() {}

            private function internal() {}

            protected function alsoInternal() {}

            public function declared() {}

            public static function make() {}

            public function forgotten() {}
        }
        PHP;

    /**
     * `__construct` is public and absent from the interface, so silence here is
     * also what proves magic methods are left out of the facade's surface.
     */
    public function test_a_facade_whose_interface_declares_every_public_method_is_allowed(): void
    {
        self::assertSame([], $this->analyse(['declared', 'forgotten']));
    }

    /**
     * `internal` and `alsoInternal` are absent from the interface too, so the
     * count is what proves non-public methods are not part of the surface.
     */
    public function test_a_public_method_missing_from_the_interface_is_reported(): void
    {
        $violations = $this->analyse(['declared']);

        self::assertCount(1, $violations);
        self::assertSame(
            'Facade method App\Checkout\CheckoutFacade::forgotten() is missing from App\Checkout\CheckoutFacadeInterface. Consumers type-hinting the interface cannot reach it: declare it in the interface, or make the method non-public.',
            $violations[0]->message,
        );
        self::assertSame('gacela.facadeInterfaceDrift', $violations[0]->identifier);
    }

    /**
     * The finding is about one method inside the class, so it has to carry that
     * method's line -- the class's own line names no method to go and declare.
     */
    public function test_the_violation_points_at_the_method_not_the_class(): void
    {
        self::assertSame(14, $this->analyse(['declared'])[0]->node?->getStartLine());
    }

    /**
     * The rule is opt-in by naming: a facade implementing something else has not
     * claimed the pairing this checks.
     */
    public function test_a_facade_implementing_an_unrelated_interface_is_not_checked(): void
    {
        $analyser = new FacadeInterfaceInSyncAnalyser();
        $class = new FakeAnalysedClass(
            'App\Checkout\CheckoutFacade',
            [AbstractFacade::class],
            ['App\Shared\Runnable' => []],
        );

        self::assertSame([], $analyser->analyse(ParseSource::classIn(self::SOURCE), $class));
    }

    public function test_a_facade_implementing_nothing_is_not_checked(): void
    {
        $analyser = new FacadeInterfaceInSyncAnalyser();
        $class = new FakeAnalysedClass('App\Checkout\CheckoutFacade', [AbstractFacade::class]);

        self::assertSame([], $analyser->analyse(ParseSource::classIn(self::SOURCE), $class));
    }

    public function test_a_class_that_is_not_a_facade_is_not_checked(): void
    {
        $analyser = new FacadeInterfaceInSyncAnalyser();
        $class = new FakeAnalysedClass(
            'App\Checkout\CheckoutFacade',
            [],
            ['App\Checkout\CheckoutFacadeInterface' => []],
        );

        self::assertSame([], $analyser->analyse(ParseSource::classIn(self::SOURCE), $class));
    }

    /**
     * The pairing is by short name, so an interface kept in another namespace
     * still counts -- and the message names where it actually lives.
     */
    public function test_the_interface_is_matched_by_short_name(): void
    {
        $analyser = new FacadeInterfaceInSyncAnalyser();
        $class = new FakeAnalysedClass(
            'App\Checkout\CheckoutFacade',
            [AbstractFacade::class],
            ['App\Contracts\CheckoutFacadeInterface' => ['declared']],
        );

        $violations = $analyser->analyse(ParseSource::classIn(self::SOURCE), $class);

        self::assertStringContainsString('missing from App\Contracts\CheckoutFacadeInterface.', $violations[0]->message);
    }

    /**
     * The drift this rule catches is a consumer holding the interface being
     * unable to reach a method. A static one is not reached through an
     * instance anyway, so requiring it would force every implementer -- test
     * doubles included -- to carry a static factory. The sibling rule already
     * treats a static method as outside the Facade's delegating surface.
     */
    public function test_a_static_method_is_not_part_of_the_interface_surface(): void
    {
        $violations = $this->analyse(['declared', 'forgotten']);

        self::assertSame([], $violations);
    }

    /**
     * @param list<string> $declaredInInterface
     *
     * @return list<Violation>
     */
    private function analyse(array $declaredInInterface): array
    {
        $analyser = new FacadeInterfaceInSyncAnalyser();
        $class = new FakeAnalysedClass(
            'App\Checkout\CheckoutFacade',
            [AbstractFacade::class],
            ['App\Checkout\CheckoutFacadeInterface' => $declaredInInterface],
        );

        return $analyser->analyse(ParseSource::classIn(self::SOURCE), $class);
    }
}
