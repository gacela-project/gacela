<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ModuleGraph;

use Gacela\Console\Domain\ModuleGraph\ModuleGraphDiffer;
use PHPUnit\Framework\TestCase;

final class ModuleGraphDifferTest extends TestCase
{
    public function test_identical_graphs_have_no_changes(): void
    {
        $graph = ['A' => ['B'], 'B' => []];

        $diff = (new ModuleGraphDiffer())->diff($graph, $graph);

        self::assertFalse($diff->hasChanges());
        self::assertSame([], $diff->addedEdges);
        self::assertSame([], $diff->removedEdges);
        self::assertSame([], $diff->addedModules);
        self::assertSame([], $diff->removedModules);
    }

    public function test_a_new_edge_between_existing_modules_is_added(): void
    {
        $diff = (new ModuleGraphDiffer())->diff(
            ['A' => [], 'B' => []],
            ['A' => ['B'], 'B' => []],
        );

        self::assertTrue($diff->hasChanges());
        self::assertSame([['from' => 'A', 'to' => 'B']], $diff->addedEdges);
        self::assertSame([], $diff->removedEdges);
        self::assertSame([], $diff->addedModules);
    }

    public function test_a_dropped_edge_between_existing_modules_is_removed(): void
    {
        $diff = (new ModuleGraphDiffer())->diff(
            ['A' => ['B'], 'B' => []],
            ['A' => [], 'B' => []],
        );

        self::assertSame([['from' => 'A', 'to' => 'B']], $diff->removedEdges);
        self::assertSame([], $diff->addedEdges);
    }

    public function test_a_new_module_reports_both_the_module_and_its_edges(): void
    {
        $diff = (new ModuleGraphDiffer())->diff(
            ['B' => []],
            ['A' => ['B'], 'B' => []],
        );

        self::assertSame(['A'], $diff->addedModules);
        self::assertSame([['from' => 'A', 'to' => 'B']], $diff->addedEdges);
    }

    public function test_a_deleted_module_reports_both_the_module_and_its_edges(): void
    {
        $diff = (new ModuleGraphDiffer())->diff(
            ['A' => ['B'], 'B' => []],
            ['B' => []],
        );

        self::assertSame(['A'], $diff->removedModules);
        self::assertSame([['from' => 'A', 'to' => 'B']], $diff->removedEdges);
    }

    public function test_added_and_removed_edges_on_the_same_module_are_both_reported(): void
    {
        $diff = (new ModuleGraphDiffer())->diff(
            ['A' => ['B'], 'B' => [], 'C' => []],
            ['A' => ['C'], 'B' => [], 'C' => []],
        );

        self::assertSame([['from' => 'A', 'to' => 'C']], $diff->addedEdges);
        self::assertSame([['from' => 'A', 'to' => 'B']], $diff->removedEdges);
    }

    public function test_module_lists_are_sorted(): void
    {
        $diff = (new ModuleGraphDiffer())->diff(
            [],
            ['Zulu' => [], 'Alpha' => [], 'Mike' => []],
        );

        self::assertSame(['Alpha', 'Mike', 'Zulu'], $diff->addedModules);
    }

    public function test_two_empty_graphs_have_no_changes(): void
    {
        self::assertFalse((new ModuleGraphDiffer())->diff([], [])->hasChanges());
    }
}
