<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\Rules\CrossModuleMethodCallAnalyser;
use Gacela\StaticAnalysis\Violation;
use PHPUnit\Framework\TestCase;

final class CrossModuleMethodCallAnalyserTest extends TestCase
{
    private const ROOT = 'App\Modules';

    private const CALLER = 'App\Modules\Checkout\CheckoutFactory';

    public function test_a_call_on_another_modules_type_is_reported(): void
    {
        $violations = $this->analyse(['App\Modules\Billing\Domain\InvoiceRepository']);

        self::assertCount(1, $violations);
        self::assertSame(
            'Class App\Modules\Checkout\CheckoutFactory calls a method on App\Modules\Billing\Domain\InvoiceRepository from another module (App\Modules\Billing). Cross-module access must go through a Facade.',
            $violations[0]->message,
        );
        self::assertSame('gacela.crossModuleMethodCall', $violations[0]->identifier);
    }

    public function test_a_call_on_a_facade_is_allowed(): void
    {
        self::assertSame([], $this->analyse(['App\Modules\Billing\BillingFacade']));
    }

    /**
     * Consumers hold the interface rather than the Facade, which is the same
     * sanctioned crossing.
     */
    public function test_a_call_on_a_facade_interface_is_allowed(): void
    {
        self::assertSame([], $this->analyse(['App\Modules\Billing\BillingFacadeInterface']));
    }

    public function test_a_call_inside_the_same_module_is_allowed(): void
    {
        self::assertSame([], $this->analyse(['App\Modules\Checkout\Domain\Basket']));
    }

    public function test_a_call_on_a_type_outside_the_root_namespace_is_allowed(): void
    {
        self::assertSame([], $this->analyse(['Vendor\Library\Thing']));
    }

    /**
     * The host could not resolve the receiver. That is not evidence of a
     * violation, and guessing would turn the rule into noise.
     */
    public function test_an_unresolved_receiver_is_not_reported(): void
    {
        self::assertSame([], $this->analyse([]));
    }

    /**
     * A union receiver is several possible classes, and each is its own
     * crossing to justify.
     */
    public function test_every_class_a_union_receiver_can_be_is_checked(): void
    {
        $violations = $this->analyse([
            'App\Modules\Billing\Domain\InvoiceRepository',
            'App\Modules\Shipping\Domain\Labels',
        ]);

        self::assertCount(2, $violations);
    }

    public function test_a_union_reports_only_the_branches_that_cross(): void
    {
        $violations = $this->analyse([
            'App\Modules\Billing\BillingFacade',
            'App\Modules\Billing\Domain\InvoiceRepository',
        ]);

        self::assertCount(1, $violations);
    }

    /**
     * The skipped branch comes first here, so ending the scan instead of
     * continuing it would swallow the crossing behind it.
     */
    public function test_a_union_is_scanned_past_a_branch_in_no_module(): void
    {
        $violations = $this->analyse([
            'Vendor\Library\Thing',
            'App\Modules\Billing\Domain\InvoiceRepository',
        ]);

        self::assertCount(1, $violations);
    }

    public function test_a_call_on_a_shared_kernel_type_is_allowed(): void
    {
        self::assertSame(
            [],
            $this->analyse(['App\Modules\Shared\Clock'], sharedNamespaces: ['App\Modules\Shared']),
        );
    }

    /**
     * The exemption is namespace-boundary aware: on the raw prefix,
     * `App\Modules\Shared` would silently exempt `App\Modules\SharedFoo` too.
     */
    public function test_a_shared_namespace_does_not_exempt_a_namespace_that_starts_with_it(): void
    {
        self::assertCount(
            1,
            $this->analyse(['App\Modules\SharedFoo\Thing'], sharedNamespaces: ['App\Modules\Shared']),
        );
    }

    public function test_a_caller_inside_a_shared_namespace_is_not_checked(): void
    {
        self::assertSame(
            [],
            $this->analyse(
                ['App\Modules\Billing\Domain\InvoiceRepository'],
                caller: 'App\Modules\Shared\Clock',
                sharedNamespaces: ['App\Modules\Shared'],
            ),
        );
    }

    /**
     * A caller with no segment under the root belongs to no module, so there is
     * no boundary for it to be on one side of.
     */
    public function test_a_caller_outside_any_module_is_not_checked(): void
    {
        self::assertSame(
            [],
            $this->analyse(['App\Modules\Billing\Domain\InvoiceRepository'], caller: 'App\Modules\Loose'),
        );
    }

    /**
     * Left out, the depth is one segment under the root.
     */
    public function test_the_module_depth_defaults_to_one_segment(): void
    {
        $analyser = new CrossModuleMethodCallAnalyser(self::ROOT);

        self::assertCount(1, $analyser->analyse(self::CALLER, ['App\Modules\Billing\Domain\InvoiceRepository']));
    }

    /**
     * With two segments naming a module, `Billing\Invoicing` and `Billing\Ledger`
     * are different modules rather than one `Billing`.
     */
    public function test_the_module_depth_is_configurable(): void
    {
        $analyser = new CrossModuleMethodCallAnalyser(self::ROOT, 2);

        self::assertCount(
            1,
            $analyser->analyse('App\Modules\Billing\Invoicing\InvoiceFactory', ['App\Modules\Billing\Ledger\Entry']),
        );
    }

    /**
     * @param list<string> $receiverClasses
     * @param list<string> $sharedNamespaces
     *
     * @return list<Violation>
     */
    private function analyse(
        array $receiverClasses,
        string $caller = self::CALLER,
        array $sharedNamespaces = [],
    ): array {
        $analyser = new CrossModuleMethodCallAnalyser(self::ROOT, 1, $sharedNamespaces);

        return $analyser->analyse($caller, $receiverClasses);
    }
}
