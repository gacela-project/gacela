<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Debug;

use Gacela\Console\Application\Debug\DependencyTreeNode;
use Gacela\Console\Application\Debug\DependencyTreeRenderer;
use Gacela\Console\Application\Debug\ProvisionStatus;
use PHPUnit\Framework\TestCase;

use function array_map;
use function strpos;
use function substr;

/**
 * The shape, not the characters. Which glyph draws a branch is a look; that a
 * child is drawn *under* its parent, and that the last child closes the branch
 * so the next line is not read as its sibling, is the behaviour.
 */
final class DependencyTreeRendererTest extends TestCase
{
    private DependencyTreeRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new DependencyTreeRenderer();
    }

    public function test_no_nodes_renders_nothing(): void
    {
        self::assertSame([], $this->renderer->render([]));
    }

    public function test_a_child_is_drawn_under_its_parent(): void
    {
        $lines = $this->renderer->render([
            $this->node('Parent', 'p', [$this->node('Child', 'c')]),
        ]);

        self::assertCount(2, $lines);
        self::assertStringContainsString('$p: Parent', $lines[0]);
        self::assertStringContainsString('$c: Child', $lines[1]);

        // The child is indented past its parent, which is what says "under".
        self::assertStringStartsWith('    ', $lines[1]);
    }

    /**
     * A middle child keeps the trunk open so the grandchild below it is not
     * read as a sibling of the *next* branch.
     */
    public function test_a_non_last_child_keeps_the_trunk_open_for_its_own_children(): void
    {
        $lines = $this->renderer->render([
            $this->node('First', 'a', [$this->node('Nested', 'n')]),
            $this->node('Second', 'b'),
        ]);

        self::assertStringContainsString('│', $lines[1], 'the grandchild hangs off a trunk that continues');
        self::assertStringContainsString('└── ', $lines[2], 'the last sibling closes the branch');
    }

    /**
     * Three levels under an indent, which is the only shape that pins how the
     * prefix accumulates: the indent has to lead every line, and each level has
     * to carry its ancestors' trunks. Two levels, or no indent, leave the order
     * and the accumulation indistinguishable.
     */
    public function test_the_prefix_accumulates_through_every_level_under_the_indent(): void
    {
        $lines = $this->renderer->render([
            $this->node('A', 'a', [
                $this->node('A1', 'a1', [$this->node('A1a', 'a1a')]),
                $this->node('A2', 'a2'),
            ]),
            $this->node('B', 'b'),
        ], '  ');

        self::assertSame(
            [
                '  ├── ',      // A: first of two roots
                '  │   ├── ',  // A1: under A, which continues
                '  │   │   └── ', // A1a: under A1, which also continues
                '  │   └── ',  // A2: last under A
                '  └── ',      // B: last root, closes
            ],
            array_map(
                static fn (string $line): string => substr($line, 0, (int)strpos($line, '<')),
                $lines,
            ),
        );
    }

    public function test_an_unprovided_node_is_marked_differently(): void
    {
        $lines = $this->renderer->render([
            $this->node('Missing', 'm', [], ProvisionStatus::Unresolvable),
        ]);

        self::assertStringContainsString('✗', $lines[0]);
        self::assertStringContainsString('(unresolvable)', $lines[0]);
        self::assertStringNotContainsString('✓', $lines[0]);
    }

    public function test_a_cut_cycle_says_so(): void
    {
        $lines = $this->renderer->render([
            $this->node('Loop', 'l', [], ProvisionStatus::Autowired, repeated: true),
        ]);

        // Without this, a branch that stopped because it looped reads exactly
        // like one that had nothing more to say.
        self::assertStringContainsString('(cycle)', $lines[0]);
    }

    public function test_a_root_node_without_a_parameter_is_not_labelled_with_one(): void
    {
        $lines = $this->renderer->render([$this->node('Bare', null)]);

        self::assertStringNotContainsString('$', $lines[0]);
        self::assertStringContainsString('Bare', $lines[0]);
    }

    public function test_the_indent_is_applied_to_every_line(): void
    {
        $lines = $this->renderer->render([
            $this->node('Parent', 'p', [$this->node('Child', 'c')]),
        ], '    ');

        foreach ($lines as $line) {
            self::assertStringStartsWith('    ', $line);
        }
    }

    /**
     * @param list<DependencyTreeNode> $children
     */
    private function node(
        string $className,
        ?string $parameter,
        array $children = [],
        ProvisionStatus $status = ProvisionStatus::Autowired,
        bool $repeated = false,
    ): DependencyTreeNode {
        return new DependencyTreeNode($className, $parameter, $status, $children, $repeated);
    }
}
