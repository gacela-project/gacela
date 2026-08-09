<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\ModuleRules\ModuleRuleSet;
use Gacela\StaticAnalysis\Rules\DeclaredModuleDependencyAnalyser;
use Gacela\StaticAnalysis\Violation;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class DeclaredModuleDependencyAnalyserTest extends TestCase
{
    private const ROOT = 'App\Modules';

    public function test_a_dependency_a_rule_denies_is_reported_with_its_reason(): void
    {
        $violations = $this->analyse('new App\Modules\Admin\AdminFacade();');

        self::assertCount(1, $violations);
        self::assertSame(
            'App\Modules\Payment must not depend on App\Modules\Admin: billing must not reach back-office',
            $violations[0]->message,
        );
        self::assertSame('gacela.declaredModuleDependency', $violations[0]->identifier);
        self::assertSame(
            'Drop the dependency on App\Modules\Admin, or change the rule that forbids it.',
            $violations[0]->tip,
        );
    }

    /**
     * The rules are about modules, not about how a boundary is spelled: going
     * through the other module's Facade is still the dependency the rule
     * forbids.
     */
    public function test_a_denied_dependency_is_reported_even_when_it_goes_through_a_facade(): void
    {
        self::assertCount(1, $this->analyse('App\Modules\Admin\AdminFacade::create();'));
    }

    public function test_a_dependency_no_rule_denies_is_left_alone(): void
    {
        self::assertSame([], $this->analyse('new App\Modules\Shipping\ShippingFacade();'));
    }

    public function test_staying_inside_the_module_is_left_alone(): void
    {
        self::assertSame([], $this->analyse('new App\Modules\Payment\Domain\Basket();'));
    }

    public function test_a_class_outside_the_root_namespace_is_not_a_module(): void
    {
        self::assertSame([], $this->analyse('new Vendor\Library\Thing();'));
    }

    public function test_a_module_no_rule_governs_is_left_alone(): void
    {
        self::assertSame(
            [],
            $this->analyse('new App\Modules\Admin\AdminFacade();', 'App\Modules\Shipping\ShippingFactory'),
        );
    }

    public function test_an_empty_rule_set_reports_nothing(): void
    {
        $analyser = new DeclaredModuleDependencyAnalyser(self::ROOT, ModuleRuleSet::empty());

        self::assertSame([], $analyser->analyse(
            ParseSource::classIn($this->sourceWith('new App\Modules\Admin\AdminFacade();')),
            new FakeAnalysedClass('App\Modules\Payment\PaymentFactory'),
        ));
    }

    public function test_one_forbidden_module_reached_many_times_is_one_finding(): void
    {
        self::assertCount(1, $this->analyse(
            'new App\Modules\Admin\AdminFacade(); App\Modules\Admin\Other::go(); $x = App\Modules\Admin\Third::class;',
        ));
    }

    /**
     * The scan cannot stop at the first reference it has nothing to say about:
     * a class reaches many others, and the forbidden one is rarely written
     * first.
     */
    public function test_a_forbidden_dependency_after_an_innocent_one_is_still_reported(): void
    {
        self::assertCount(1, $this->analyse(
            'new App\Modules\Payment\Domain\Basket(); new App\Modules\Shipping\ShippingFacade(); new App\Modules\Admin\AdminFacade();',
        ));
    }

    public function test_a_second_forbidden_module_after_a_repeated_one_is_still_reported(): void
    {
        $rules = ModuleRuleSet::fromDecodedJson(['rules' => [
            [
                'from' => 'App\Modules\Payment',
                'deny' => ['App\Modules\Admin', 'App\Modules\Legacy'],
                'reason' => 'reviewed',
            ],
        ]]);

        $analyser = new DeclaredModuleDependencyAnalyser(self::ROOT, $rules);

        $violations = $analyser->analyse(
            ParseSource::classIn($this->sourceWith(
                'new App\Modules\Admin\AdminFacade(); App\Modules\Admin\Other::go(); new App\Modules\Legacy\LegacyFacade();',
            )),
            new FakeAnalysedClass('App\Modules\Payment\PaymentFactory'),
        );

        self::assertCount(2, $violations);
    }

    /**
     * A type-hint costs the file the same import the module graph is built
     * from, so the CLI gate and the editor have to agree it is a dependency.
     */
    public function test_a_dependency_written_only_as_a_type_hint_is_reported(): void
    {
        $source = <<<'PHP'
            <?php

            namespace App\Modules\Payment;

            final class PaymentFactory
            {
                public function create(\App\Modules\Admin\AdminFacade $admin): void
                {
                }
            }
            PHP;

        $analyser = new DeclaredModuleDependencyAnalyser(self::ROOT, $this->rules());

        self::assertCount(1, $analyser->analyse(
            ParseSource::classInAsPhpStanResolves($source),
            new FakeAnalysedClass('App\Modules\Payment\PaymentFactory'),
        ));
    }

    /**
     * PHPStan rewrites names in place; Psalm leaves the source text and puts the
     * qualified form on an attribute. A rule reading only the written name would
     * hold in one host and pass silently in the other.
     */
    public function test_an_imported_class_is_matched_whichever_way_the_host_resolved_it(): void
    {
        $source = <<<'PHP'
            <?php

            namespace App\Modules\Payment;

            use App\Modules\Admin\AdminFacade;

            final class PaymentFactory
            {
                public function create(): void
                {
                    new AdminFacade();
                }
            }
            PHP;

        $analyser = new DeclaredModuleDependencyAnalyser(self::ROOT, $this->rules());
        $class = new FakeAnalysedClass('App\Modules\Payment\PaymentFactory');

        self::assertCount(1, $analyser->analyse(ParseSource::classInAsPhpStanResolves($source), $class));
        self::assertCount(1, $analyser->analyse(ParseSource::classInWithNameAttributes($source), $class));
    }

    public function test_a_shared_namespace_is_never_checked(): void
    {
        self::assertSame([], $this->analyse(
            'new App\Modules\Admin\AdminFacade();',
            'App\Modules\Payment\PaymentFactory',
            ['App\Modules\Payment'],
        ));
    }

    public function test_an_allow_list_reports_the_dependency_it_does_not_permit(): void
    {
        $rules = ModuleRuleSet::fromDecodedJson(['rules' => [
            ['from' => 'App\Modules\Payment', 'allow' => ['App\Modules\Shipping'], 'reason' => 'payment reads shipping only'],
        ]]);

        $analyser = new DeclaredModuleDependencyAnalyser(self::ROOT, $rules);

        $violations = $analyser->analyse(
            ParseSource::classIn($this->sourceWith('new App\Modules\Admin\AdminFacade();')),
            new FakeAnalysedClass('App\Modules\Payment\PaymentFactory'),
        );

        self::assertCount(1, $violations);
        self::assertStringContainsString('payment reads shipping only', $violations[0]->message);
    }

    /**
     * @param list<string> $sharedNamespaces
     *
     * @return list<Violation>
     */
    private function analyse(
        string $body,
        string $className = 'App\Modules\Payment\PaymentFactory',
        array $sharedNamespaces = [],
    ): array {
        $analyser = new DeclaredModuleDependencyAnalyser(self::ROOT, $this->rules(), 1, $sharedNamespaces);

        return $analyser->analyse(
            ParseSource::classIn($this->sourceWith($body)),
            new FakeAnalysedClass($className),
        );
    }

    private function rules(): ModuleRuleSet
    {
        return ModuleRuleSet::fromDecodedJson(['rules' => [
            [
                'from' => 'App\Modules\Payment',
                'deny' => ['App\Modules\Admin'],
                'reason' => 'billing must not reach back-office',
            ],
        ]]);
    }

    private function sourceWith(string $body): string
    {
        return sprintf("<?php\nfinal class PaymentFactory\n{\n    public function create()\n    {\n%s\n    }\n}", $body);
    }
}
