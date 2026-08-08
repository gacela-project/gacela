<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ModuleGraph;

use Gacela\Console\Domain\ModuleGraph\PhpImportParser;
use PHPUnit\Framework\TestCase;

final class PhpImportParserTest extends TestCase
{
    public function test_a_simple_import(): void
    {
        self::assertSame(
            ['App\Billing\Invoice'],
            $this->parse('<?php use App\Billing\Invoice;'),
        );
    }

    public function test_several_imports(): void
    {
        self::assertSame(
            ['App\Billing\Invoice', 'App\Orders\Order'],
            $this->parse("<?php\nuse App\Billing\Invoice;\nuse App\Orders\Order;"),
        );
    }

    public function test_an_aliased_import_keeps_the_original_name(): void
    {
        self::assertSame(
            ['App\Billing\Invoice'],
            $this->parse('<?php use App\Billing\Invoice as BillingInvoice;'),
        );
    }

    /**
     * The case the regex could not see at all: it stopped at the brace and
     * returned the bare prefix, so neither module produced an edge.
     */
    public function test_a_grouped_import_spanning_two_modules(): void
    {
        self::assertSame(
            ['App\Billing\Invoice', 'App\Orders\Order'],
            $this->parse('<?php use App\{Billing\Invoice, Orders\Order};'),
        );
    }

    public function test_a_grouped_import_split_across_lines(): void
    {
        $source = <<<'PHP'
            <?php

            use App\{
                Billing\Invoice,
                Orders\Order,
            };
            PHP;

        self::assertSame(['App\Billing\Invoice', 'App\Orders\Order'], $this->parse($source));
    }

    public function test_a_grouped_import_with_an_alias(): void
    {
        self::assertSame(
            ['App\Billing\Invoice', 'App\Orders\Order'],
            $this->parse('<?php use App\{Billing\Invoice as Inv, Orders\Order};'),
        );
    }

    public function test_a_function_import_is_not_a_class_import(): void
    {
        self::assertSame([], $this->parse('<?php use function App\Billing\calculate;'));
    }

    public function test_a_const_import_is_not_a_class_import(): void
    {
        self::assertSame([], $this->parse('<?php use const App\Billing\RATE;'));
    }

    /**
     * The modifier applies to every entry in the statement, not just the first.
     */
    public function test_a_function_import_list_is_skipped_entirely(): void
    {
        self::assertSame(
            [],
            $this->parse('<?php use function App\Billing\calculate, App\Orders\total;'),
        );
    }

    /**
     * PHP allows the modifier per entry inside a group, so the class entries in
     * a mixed group still count.
     */
    public function test_a_mixed_group_keeps_only_the_class_entries(): void
    {
        self::assertSame(
            ['App\Billing\Invoice'],
            $this->parse('<?php use App\{Billing\Invoice, function Orders\total};'),
        );
    }

    /**
     * The skipped entry comes first here, so a `break` in place of the skip
     * would swallow the class entry behind it.
     */
    public function test_a_mixed_group_starting_with_a_function_keeps_the_rest(): void
    {
        self::assertSame(
            ['App\Orders\Order'],
            $this->parse('<?php use App\{function Billing\total, Orders\Order};'),
        );
    }

    /**
     * One statement, several imports, no group.
     */
    public function test_a_comma_separated_statement_yields_every_entry(): void
    {
        self::assertSame(
            ['App\Billing\Invoice', 'App\Orders\Order'],
            $this->parse('<?php use App\Billing\Invoice, App\Orders\Order;'),
        );
    }

    /**
     * A `use` inside a class body imports a trait, which is a different
     * relationship. Collecting it would change the graph rather than fix it.
     */
    public function test_a_trait_use_inside_a_class_is_not_an_import(): void
    {
        $source = <<<'PHP'
            <?php

            use App\Billing\Invoice;

            final class Foo
            {
                use App\Shared\SomeTrait;
            }
            PHP;

        self::assertSame(['App\Billing\Invoice'], $this->parse($source));
    }

    /**
     * The depth counter has to come back to zero when the class body closes,
     * not merely go up when it opens: an import after the class is top level
     * again and must still be collected.
     */
    public function test_an_import_after_a_class_body_is_still_collected(): void
    {
        $source = <<<'PHP'
            <?php

            use App\Billing\Invoice;

            final class Foo
            {
                use App\Shared\SomeTrait;
            }

            use App\Orders\Order;
            PHP;

        self::assertSame(['App\Billing\Invoice', 'App\Orders\Order'], $this->parse($source));
    }

    public function test_a_leading_separator_is_kept_verbatim(): void
    {
        self::assertSame(['App\Billing\Invoice'], $this->parse('<?php use App\Billing\Invoice;'));
    }

    public function test_a_file_with_no_imports(): void
    {
        self::assertSame([], $this->parse('<?php final class Foo {}'));
    }

    /**
     * @return list<string>
     */
    private function parse(string $source): array
    {
        return (new PhpImportParser())->importsIn($source);
    }
}
