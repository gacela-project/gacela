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

    /**
     * A leading separator is legal and means the same import. Keeping it would
     * make the name miss every module, because module names carry no separator.
     */
    public function test_a_leading_separator_is_dropped(): void
    {
        self::assertSame(['App\Billing\Invoice'], $this->parse('<?php use \App\Billing\Invoice;'));
    }

    public function test_a_leading_separator_is_dropped_on_a_group_prefix(): void
    {
        self::assertSame(
            ['App\Billing\Invoice', 'App\Orders\Order'],
            $this->parse('<?php use \App\{Billing\Invoice, Orders\Order};'),
        );
    }

    /**
     * PHP keywords are case-insensitive, so the uppercase form is the same
     * statement. Reading it as a class name leaks an edge from the group prefix.
     */
    public function test_an_uppercase_function_import_is_not_a_class_import(): void
    {
        self::assertSame([], $this->parse('<?php use FUNCTION App\Billing\calculate;'));
    }

    public function test_a_mixed_case_const_import_is_not_a_class_import(): void
    {
        self::assertSame([], $this->parse('<?php use Const App\Billing\RATE;'));
    }

    public function test_an_uppercase_function_entry_in_a_group_is_skipped(): void
    {
        self::assertSame(
            ['App\Orders\Order'],
            $this->parse('<?php use App\{FUNCTION Billing\total, Orders\Order};'),
        );
    }

    /**
     * The keyword may be followed by any whitespace, not only a single space.
     */
    public function test_a_function_import_split_after_the_keyword_is_skipped(): void
    {
        self::assertSame([], $this->parse("<?php use function\n    App\Billing\calculate;"));
    }

    /**
     * The name starts with the keyword text, so only the required whitespace
     * keeps this a class import rather than a skipped function import.
     */
    public function test_a_namespace_whose_name_starts_with_a_keyword_is_an_import(): void
    {
        self::assertSame(['Functional\Billing\Invoice'], $this->parse('<?php use Functional\Billing\Invoice;'));
    }

    public function test_a_file_with_no_imports(): void
    {
        self::assertSame([], $this->parse('<?php final class Foo {}'));
    }

    public function test_an_import_carries_the_line_it_is_declared_on(): void
    {
        $source = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Reporting;

            use App\Billing\Invoice;
            use App\Orders\Order;
            PHP;

        self::assertSame(
            [
                ['name' => 'App\Billing\Invoice', 'line' => 7],
                ['name' => 'App\Orders\Order', 'line' => 8],
            ],
            $this->parseWithLines($source),
        );
    }

    /**
     * Every name in one statement reports the line the statement starts on --
     * the `use` keyword -- rather than the line its own entry sits on. A caller
     * pointing a reader at the import has the statement to show them, and
     * splitting the entries apart would mean re-tokenizing the group body for a
     * distinction nobody reading a violation needs.
     */
    public function test_every_name_of_a_multi_line_group_reports_the_line_the_statement_opens_on(): void
    {
        $source = <<<'PHP'
            <?php

            use App\{
                Billing\Invoice,
                Orders\Order,
            };
            PHP;

        self::assertSame(
            [
                ['name' => 'App\Billing\Invoice', 'line' => 3],
                ['name' => 'App\Orders\Order', 'line' => 3],
            ],
            $this->parseWithLines($source),
        );
    }

    /**
     * The trait `use` is skipped, so the import after the class body keeps its
     * own line instead of inheriting the position of whatever was counted last.
     */
    public function test_a_skipped_trait_use_does_not_shift_the_lines_that_follow(): void
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

        self::assertSame(
            [
                ['name' => 'App\Billing\Invoice', 'line' => 3],
                ['name' => 'App\Orders\Order', 'line' => 10],
            ],
            $this->parseWithLines($source),
        );
    }

    /**
     * The two methods answer the same question, so the names must not drift
     * apart: one is the other with the positions dropped.
     */
    public function test_the_names_are_the_same_whether_or_not_the_lines_are_asked_for(): void
    {
        $source = <<<'PHP'
            <?php

            use App\{Billing\Invoice, function Orders\total};
            use App\Orders\Order as SalesOrder;
            use function App\Billing\calculate;
            PHP;

        $parser = new PhpImportParser();

        self::assertSame(
            $parser->importsIn($source),
            array_column($parser->importsWithLinesIn($source), 'name'),
        );
    }

    /**
     * @return list<string>
     */
    private function parse(string $source): array
    {
        return (new PhpImportParser())->importsIn($source);
    }

    /**
     * @return list<array{name: string, line: int}>
     */
    private function parseWithLines(string $source): array
    {
        return (new PhpImportParser())->importsWithLinesIn($source);
    }
}
