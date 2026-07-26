<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ModuleGraph;

use Gacela\Console\Domain\ModuleGraph\ModuleCycleDetector;
use PHPUnit\Framework\TestCase;

final class ModuleCycleDetectorTest extends TestCase
{
    private ModuleCycleDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new ModuleCycleDetector();
    }

    public function test_an_acyclic_graph_has_no_cycles(): void
    {
        self::assertSame([], $this->detector->detect([
            'A' => ['B', 'C'],
            'B' => ['C'],
            'C' => [],
        ]));
    }

    public function test_an_empty_graph_has_no_cycles(): void
    {
        self::assertSame([], $this->detector->detect([]));
    }

    public function test_finds_a_two_module_cycle(): void
    {
        self::assertSame([['A', 'B']], $this->detector->detect([
            'A' => ['B'],
            'B' => ['A'],
        ]));
    }

    public function test_finds_a_longer_cycle(): void
    {
        self::assertSame([['A', 'B', 'C']], $this->detector->detect([
            'A' => ['B'],
            'B' => ['C'],
            'C' => ['A'],
        ]));
    }

    public function test_a_module_importing_itself_is_a_cycle(): void
    {
        self::assertSame([['A']], $this->detector->detect([
            'A' => ['A'],
        ]));
    }

    public function test_a_module_with_no_self_edge_is_not_a_cycle(): void
    {
        self::assertSame([], $this->detector->detect([
            'A' => [],
        ]));
    }

    /**
     * Reporting only the first cycle would let a fix look complete while the
     * rest of the graph is still tangled.
     */
    public function test_reports_every_independent_cycle_not_just_the_first(): void
    {
        self::assertSame(
            [['A', 'B'], ['C', 'D']],
            $this->detector->detect([
                'A' => ['B'],
                'B' => ['A'],
                'C' => ['D'],
                'D' => ['C'],
            ]),
        );
    }

    public function test_cycles_are_reported_with_their_modules_sorted(): void
    {
        self::assertSame([['Alpha', 'Zulu']], $this->detector->detect([
            'Zulu' => ['Alpha'],
            'Alpha' => ['Zulu'],
        ]));
    }

    /**
     * Output order must not depend on the order the traversal happened to find
     * the components, or a stable graph produces an unstable report.
     */
    public function test_cycles_are_ordered_independently_of_discovery_order(): void
    {
        self::assertSame(
            [['Alpha', 'Zulu'], ['Beta', 'Gamma']],
            $this->detector->detect([
                'Beta' => ['Gamma'],
                'Gamma' => ['Beta'],
                'Alpha' => ['Zulu'],
                'Zulu' => ['Alpha'],
            ]),
        );
    }

    /**
     * The mirror of the test above: the same expected order must come out when
     * the components are discovered the other way round.
     */
    public function test_cycles_are_ordered_when_discovered_in_sorted_order_too(): void
    {
        self::assertSame(
            [['Alpha', 'Zulu'], ['Beta', 'Gamma']],
            $this->detector->detect([
                'Alpha' => ['Zulu'],
                'Zulu' => ['Alpha'],
                'Beta' => ['Gamma'],
                'Gamma' => ['Beta'],
            ]),
        );
    }

    public function test_edges_pointing_outside_the_graph_are_ignored(): void
    {
        self::assertSame([], $this->detector->detect([
            'A' => ['NotAModuleInThisGraph'],
        ]));
    }

    /**
     * An edge leaving the graph must skip that edge, not abandon the rest of
     * the module's imports -- the cycle can be behind it.
     */
    public function test_an_external_edge_does_not_hide_a_later_cycle_on_the_same_module(): void
    {
        self::assertSame([['A', 'B']], $this->detector->detect([
            'A' => ['AAA_NotInThisGraph', 'B'],
            'B' => ['A'],
        ]));
    }

    /**
     * After recursing into a neighbour the scan must resume at the *next* one:
     * skipping an edge silently loses every cycle behind it.
     */
    public function test_resumes_at_the_next_neighbour_after_recursing(): void
    {
        self::assertSame([['A', 'C']], $this->detector->detect([
            'A' => ['B', 'C'],
            'B' => [],
            'C' => ['A'],
        ]));
    }

    /**
     * A module that is its own component and not a cycle must be skipped, not
     * end the scan: real graphs are mostly such modules.
     */
    public function test_a_non_cyclic_component_found_first_does_not_end_the_scan(): void
    {
        self::assertSame([['B', 'C']], $this->detector->detect([
            'A' => [],
            'B' => ['C'],
            'C' => ['B'],
        ]));
    }

    /**
     * Once a component is closed its members are off the stack; treating them as
     * still on it merges unrelated components into one bogus cycle.
     */
    public function test_a_closed_component_referenced_later_stays_separate(): void
    {
        self::assertSame(
            [['C', 'D'], ['E', 'F']],
            $this->detector->detect([
                'C' => ['D'],
                'D' => ['C'],
                // E points at C, whose component has already closed by the time
                // E is reached. Treating C as still on the stack drags E's
                // low-link down and its own component never closes.
                'E' => ['C', 'F'],
                'F' => ['E'],
            ]),
        );
    }

    public function test_a_cycle_reachable_only_through_an_acyclic_prefix_is_found(): void
    {
        self::assertSame([['B', 'C']], $this->detector->detect([
            'A' => ['B'],
            'B' => ['C'],
            'C' => ['B'],
        ]));
    }
}
