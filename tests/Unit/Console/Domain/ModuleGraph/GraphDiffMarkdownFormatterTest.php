<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ModuleGraph;

use Gacela\Console\Domain\ModuleGraph\GraphDiffMarkdownFormatter;
use Gacela\Console\Domain\ModuleGraph\GraphDiffResult;
use PHPUnit\Framework\TestCase;

final class GraphDiffMarkdownFormatterTest extends TestCase
{
    public function test_starts_with_the_marker_so_ci_can_find_its_own_comment(): void
    {
        $markdown = (new GraphDiffMarkdownFormatter())->format(
            new GraphDiffResult([], [], [['from' => 'A', 'to' => 'B']], []),
            ['A' => ['B'], 'B' => []],
        );

        self::assertStringStartsWith(GraphDiffMarkdownFormatter::MARKER, $markdown);
        self::assertSame('<!-- gacela-module-graph -->', GraphDiffMarkdownFormatter::MARKER);
    }

    public function test_added_edge_is_listed_and_drawn_thick(): void
    {
        $markdown = (new GraphDiffMarkdownFormatter())->format(
            new GraphDiffResult([], [], [['from' => 'App\\A', 'to' => 'App\\B']], []),
            ['App\\A' => ['App\\B'], 'App\\B' => []],
        );

        self::assertStringContainsString('- **new dependency** `App\\A` → `App\\B`', $markdown);
        self::assertStringContainsString('App.A ==> App.B', $markdown);
        self::assertStringNotContainsString('App.A --> App.B', $markdown);
    }

    public function test_unchanged_edge_between_affected_modules_is_drawn_thin_and_not_listed(): void
    {
        $markdown = (new GraphDiffMarkdownFormatter())->format(
            new GraphDiffResult([], [], [['from' => 'A', 'to' => 'C']], []),
            ['A' => ['B', 'C'], 'B' => [], 'C' => []],
        );

        self::assertStringContainsString('A ==> C', $markdown);
        self::assertStringNotContainsString('- **new dependency** `A` → `B`', $markdown);
    }

    /**
     * Rendering the whole graph is what makes this kind of bot get muted: on a
     * mid-sized app the two nodes that moved arrive behind dozens that did not.
     */
    public function test_modules_the_change_does_not_touch_are_left_out(): void
    {
        $markdown = (new GraphDiffMarkdownFormatter())->format(
            new GraphDiffResult([], [], [['from' => 'A', 'to' => 'B']], []),
            ['A' => ['B'], 'B' => [], 'Unrelated' => ['AlsoUnrelated'], 'AlsoUnrelated' => []],
        );

        self::assertStringContainsString('A ==> B', $markdown);
        self::assertStringNotContainsString('Unrelated', $markdown);
    }

    public function test_says_where_the_full_graph_lives(): void
    {
        $markdown = (new GraphDiffMarkdownFormatter())->format(
            new GraphDiffResult(['A'], [], [], []),
            ['A' => []],
        );

        self::assertStringContainsString(
            'Only the modules this change touches are drawn; the full graph is in the `module-graph` artifact.',
            $markdown,
        );
    }

    public function test_removed_edge_is_drawn_back_in_dashed(): void
    {
        $markdown = (new GraphDiffMarkdownFormatter())->format(
            new GraphDiffResult([], [], [], [['from' => 'A', 'to' => 'B']]),
            ['A' => [], 'B' => []],
        );

        self::assertStringContainsString('- **removed dependency** `A` → `B`', $markdown);
        self::assertStringContainsString('A -.-> B', $markdown);
    }

    public function test_added_and_removed_modules_are_listed(): void
    {
        $markdown = (new GraphDiffMarkdownFormatter())->format(
            new GraphDiffResult(['New'], ['Gone'], [], []),
            ['New' => []],
        );

        self::assertStringContainsString('- **new module** `New`', $markdown);
        self::assertStringContainsString('- **removed module** `Gone`', $markdown);
    }

    public function test_renders_a_mermaid_block_with_a_legend(): void
    {
        $markdown = (new GraphDiffMarkdownFormatter())->format(
            new GraphDiffResult(['A'], [], [], []),
            ['A' => []],
        );

        self::assertStringContainsString("```mermaid\ngraph TD", $markdown);
        self::assertStringContainsString('```', $markdown);
        self::assertStringContainsString(
            'Legend: `==>` new dependency · `-.->` removed dependency · `-->` unchanged.',
            $markdown,
        );
    }

    public function test_dependency_free_module_is_drawn_as_a_bare_node(): void
    {
        $markdown = (new GraphDiffMarkdownFormatter())->format(
            new GraphDiffResult(['App\\Lonely'], [], [], []),
            ['App\\Lonely' => []],
        );

        self::assertStringContainsString("    App.Lonely\n", $markdown);
    }

    public function test_the_report_ends_with_a_newline(): void
    {
        $markdown = (new GraphDiffMarkdownFormatter())->format(
            new GraphDiffResult(['A'], [], [], []),
            ['A' => []],
        );

        self::assertStringEndsWith("artifact.\n", $markdown);
    }

    /**
     * An edge is only drawn when *both* ends are part of the change. Drawing it
     * when either end matches pulls in every unchanged module that happens to
     * touch a changed one, which for a hub module is the whole graph.
     */
    public function test_an_edge_with_only_one_affected_end_is_not_drawn(): void
    {
        $markdown = (new GraphDiffMarkdownFormatter())->format(
            new GraphDiffResult(['App\\New'], [], [], []),
            ['App\\Hub' => ['App\\New'], 'App\\New' => [], 'App\\Other' => ['App\\Hub']],
        );

        self::assertStringNotContainsString('App.Hub', $markdown);
        self::assertStringContainsString('    App.New', $markdown);
    }

    public function test_heading_states_what_changed(): void
    {
        $markdown = (new GraphDiffMarkdownFormatter())->format(
            new GraphDiffResult(['A'], [], [], []),
            ['A' => []],
        );

        self::assertStringContainsString('### Module dependency graph changed', $markdown);
    }
}
