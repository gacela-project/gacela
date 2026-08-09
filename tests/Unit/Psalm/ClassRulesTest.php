<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Psalm\ClassRules;
use Gacela\StaticAnalysis\Violation;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Which rules Psalm runs, driven directly.
 *
 * `ArchitectureRulesTest` proves the same thing through a real `vendor/bin/psalm`
 * -- the stronger check -- but that is a subprocess, so coverage cannot see it
 * and every mutant in this class counted as untested. Reporting stays out of
 * reach here: it goes through Psalm's `IssueBuffer`, which wants a live
 * `ProjectAnalyzer`.
 */
final class ClassRulesTest extends TestCase
{
    public function test_a_module_that_follows_the_rules_is_silent(): void
    {
        $source = <<<'PHP'
            <?php
            final class CheckoutFacade
            {
                public function doThing()
                {
                    return $this->getFactory()->createThing();
                }
            }
            PHP;

        self::assertSame([], $this->violationsIn($source, [AbstractFacade::class]));
    }

    public function test_it_runs_the_suffix_rule(): void
    {
        self::assertSame(
            ['gacela.suffixExtends'],
            $this->identifiersIn('<?php final class CheckoutFacade {}'),
        );
    }

    /**
     * One instance per pillar, so a `*Config` is checked by the same handler
     * that checks a `*Facade`.
     */
    public function test_it_runs_the_suffix_rule_for_every_pillar(): void
    {
        foreach (['Facade', 'Factory', 'Provider', 'Config'] as $pillar) {
            self::assertSame(
                ['gacela.suffixExtends'],
                $this->identifiersIn(
                    '<?php final class Checkout' . $pillar . ' {}',
                    className: 'App\Checkout\Checkout' . $pillar,
                ),
                $pillar . ' is a pillar and must be checked',
            );
        }
    }

    public function test_it_runs_the_factory_rule(): void
    {
        $source = <<<'PHP'
            <?php
            final class CheckoutFactory
            {
                public function create()
                {
                    return $this->getFacade()->doThing();
                }
            }
            PHP;

        self::assertSame(
            ['gacela.factoryCallsGetFacade'],
            $this->identifiersIn($source, [AbstractFactory::class], 'App\Checkout\CheckoutFactory'),
        );
    }

    public function test_it_runs_the_interface_drift_rule(): void
    {
        $source = <<<'PHP'
            <?php
            final class CheckoutFacade implements CheckoutFacadeInterface
            {
                public function forgotten()
                {
                    return $this->getFactory()->createThing();
                }
            }
            PHP;

        $violations = ClassRules::violationsIn(
            ParseSource::classIn($source),
            new FakeAnalysedClass(
                'App\Checkout\CheckoutFacade',
                [AbstractFacade::class],
                ['App\Checkout\CheckoutFacadeInterface' => []],
            ),
        );

        self::assertSame(['gacela.facadeInterfaceDrift'], $this->identifiers($violations));
    }

    /**
     * The facade-method rule is run per method from here rather than from a
     * function-like handler of its own, so it has to be reached at all.
     */
    public function test_it_runs_the_facade_method_rule(): void
    {
        $source = <<<'PHP'
            <?php
            final class CheckoutFacade
            {
                public function doesArithmetic()
                {
                    return 1 + 1;
                }
            }
            PHP;

        self::assertSame(
            ['gacela.facadeOnlyDelegates'],
            $this->identifiersIn($source, [AbstractFacade::class]),
        );
    }

    /**
     * Every finding is returned, not the first. A facade with two methods that
     * both hold logic has two things to fix, and reporting one of them would
     * make the second appear only after the first was corrected.
     */
    public function test_it_returns_every_finding_it_makes(): void
    {
        $source = <<<'PHP'
            <?php
            final class CheckoutFacade
            {
                public function first()
                {
                    return 1 + 1;
                }

                public function second()
                {
                    return 2 + 2;
                }
            }
            PHP;

        self::assertSame(
            ['gacela.facadeOnlyDelegates', 'gacela.facadeOnlyDelegates'],
            $this->identifiersIn($source, [AbstractFacade::class]),
        );
    }

    /**
     * A method's finding belongs on the method. Left unpinned it would be
     * reported at the class declaration, which names no method to go and fix.
     */
    public function test_a_method_finding_is_pinned_to_its_method(): void
    {
        $source = <<<'PHP'
            <?php
            final class CheckoutFacade
            {
                public function doesArithmetic()
                {
                    return 1 + 1;
                }
            }
            PHP;

        $violations = $this->violationsIn($source, [AbstractFacade::class]);

        self::assertSame(4, $violations[0]->node?->getStartLine());
    }

    /**
     * A class-level finding carries no node of its own, so the host reports it
     * where it is already looking: at the class.
     */
    public function test_a_class_finding_carries_no_node_of_its_own(): void
    {
        $violations = $this->violationsIn('<?php final class CheckoutFacade {}');

        self::assertNull($violations[0]->node);
    }

    /**
     * @param list<string> $parents
     *
     * @return list<string>
     */
    private function identifiersIn(
        string $source,
        array $parents = [],
        string $className = 'App\Checkout\CheckoutFacade',
    ): array {
        return $this->identifiers($this->violationsIn($source, $parents, $className));
    }

    /**
     * @param list<string> $parents
     *
     * @return list<Violation>
     */
    private function violationsIn(
        string $source,
        array $parents = [],
        string $className = 'App\Checkout\CheckoutFacade',
    ): array {
        return ClassRules::violationsIn(
            ParseSource::classIn($source),
            new FakeAnalysedClass($className, $parents),
        );
    }

    /**
     * @param list<Violation> $violations
     *
     * @return list<string>
     */
    private function identifiers(array $violations): array
    {
        return array_map(static fn (Violation $v): string => $v->identifier, $violations);
    }
}
