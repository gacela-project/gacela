<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\Rules\CrossModuleViaFacadeAnalyser;
use Gacela\StaticAnalysis\Violation;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class CrossModuleViaFacadeAnalyserTest extends TestCase
{
    private const ROOT = 'App\Modules';

    public function test_reaching_into_another_module_is_reported(): void
    {
        $violations = $this->analyse('new App\Modules\Billing\Domain\InvoiceRepository();');

        self::assertCount(1, $violations);
        self::assertSame(
            'Class App\Modules\Checkout\CheckoutFactory references App\Modules\Billing\Domain\InvoiceRepository from another module (App\Modules\Billing). Cross-module access must go through a Facade.',
            $violations[0]->message,
        );
        self::assertSame('gacela.crossModuleWithoutFacade', $violations[0]->identifier);
    }

    public function test_reaching_into_another_module_through_its_facade_is_allowed(): void
    {
        self::assertSame([], $this->analyse('new App\Modules\Billing\BillingFacade();'));
    }

    public function test_staying_inside_the_module_is_allowed(): void
    {
        self::assertSame([], $this->analyse('new App\Modules\Checkout\Domain\Basket();'));
    }

    public function test_a_class_outside_the_root_namespace_is_not_a_module(): void
    {
        self::assertSame([], $this->analyse('new Vendor\Library\Thing();'));
    }

    /**
     * Static calls, class constants and static properties all write another
     * module's name just as plainly as `new` does.
     */
    public function test_every_syntactic_reference_kind_is_reported(): void
    {
        self::assertCount(1, $this->analyse('App\Modules\Billing\Domain\Rate::compute();'));
        self::assertCount(1, $this->analyse('$x = App\Modules\Billing\Domain\Rate::DEFAULT;'));
        self::assertCount(1, $this->analyse('$x = App\Modules\Billing\Domain\Rate::$instances;'));
    }

    /**
     * `new $class` names nothing to resolve a module from.
     */
    public function test_a_dynamic_reference_is_not_reported(): void
    {
        self::assertSame([], $this->analyse('new $someClass();'));
    }

    /**
     * One class referenced twenty times is one boundary to fix.
     */
    public function test_the_same_class_referenced_twice_is_reported_once(): void
    {
        self::assertCount(1, $this->analyse(
            "new App\Modules\Billing\Domain\InvoiceRepository();\nnew App\Modules\Billing\Domain\InvoiceRepository();",
        ));
    }

    public function test_two_classes_from_the_same_module_are_both_reported(): void
    {
        self::assertCount(2, $this->analyse(
            "new App\Modules\Billing\Domain\InvoiceRepository();\nnew App\Modules\Billing\Domain\InvoiceWriter();",
        ));
    }

    /**
     * A class sitting directly under the root has no module segment beneath it,
     * so there is no boundary to be on either side of.
     */
    public function test_a_class_with_no_segment_under_the_root_is_not_in_a_module(): void
    {
        self::assertSame([], $this->analyse(
            'new App\Modules\Billing\Domain\InvoiceRepository();',
            'App\Modules\OnlyOneSegment',
        ));
    }

    public function test_a_referenced_class_with_no_segment_under_the_root_is_not_reported(): void
    {
        self::assertSame([], $this->analyse('new App\Modules\Loose();'));
    }

    /**
     * Left out, the depth is one segment under the root -- the layout every
     * other test here states explicitly.
     */
    public function test_the_module_depth_defaults_to_one_segment(): void
    {
        $analyser = new CrossModuleViaFacadeAnalyser(self::ROOT);
        $node = ParseSource::classIn($this->sourceWith('new App\Modules\Billing\Domain\InvoiceRepository();'));

        $violations = $analyser->analyse($node, new FakeAnalysedClass('App\Modules\Checkout\CheckoutFactory'));

        self::assertCount(1, $violations);
        self::assertStringContainsString('(App\Modules\Billing).', $violations[0]->message);
    }

    /**
     * With two segments naming a module, `Billing\Invoicing` and
     * `Billing\Ledger` are different modules rather than one `Billing`.
     */
    public function test_the_module_depth_is_configurable(): void
    {
        $analyser = new CrossModuleViaFacadeAnalyser(self::ROOT, 2);
        $node = ParseSource::classIn($this->sourceWith('new App\Modules\Billing\Ledger\Entry();'));
        $class = new FakeAnalysedClass('App\Modules\Billing\Invoicing\InvoiceFactory');

        self::assertCount(1, $analyser->analyse($node, $class));
    }

    public function test_a_shared_namespace_may_be_reached_from_anywhere(): void
    {
        self::assertSame(
            [],
            $this->analyse('new App\Modules\Shared\Clock();', 'App\Modules\Checkout\CheckoutFactory', ['App\Modules\Shared']),
        );
    }

    public function test_a_class_inside_a_shared_namespace_is_not_checked(): void
    {
        self::assertSame(
            [],
            $this->analyse('new App\Modules\Billing\Domain\InvoiceRepository();', 'App\Modules\Shared\Clock', ['App\Modules\Shared']),
        );
    }

    /**
     * The exemption is namespace-boundary aware: matching on the raw prefix
     * would silently exempt every module whose name merely starts with it.
     */
    public function test_a_shared_namespace_does_not_exempt_a_namespace_that_starts_with_it(): void
    {
        self::assertCount(
            1,
            $this->analyse('new App\Modules\SharedFoo\Thing();', 'App\Modules\Checkout\CheckoutFactory', ['App\Modules\Shared']),
        );
    }

    /**
     * @param list<string> $sharedNamespaces
     *
     * @return list<Violation>
     */
    private function analyse(
        string $body,
        string $className = 'App\Modules\Checkout\CheckoutFactory',
        array $sharedNamespaces = [],
    ): array {
        $analyser = new CrossModuleViaFacadeAnalyser(self::ROOT, 1, $sharedNamespaces);

        return $analyser->analyse(
            ParseSource::classIn($this->sourceWith($body)),
            new FakeAnalysedClass($className),
        );
    }

    private function sourceWith(string $body): string
    {
        return sprintf("<?php\nfinal class CheckoutFactory\n{\n    public function create()\n    {\n%s\n    }\n}", $body);
    }
}
