<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

use function array_merge;
use function count;
use function sprintf;

/**
 * Draws a {@see DependencyTreeNode} tree as indented lines.
 *
 * Three commands report the same graph -- `debug:dependencies --tree`,
 * `debug:module` and `debug:container` -- and each used to render it its own
 * way, two of them as a flat list under a heading that said "tree". Producing
 * lines rather than writing to output keeps the drawing in one place while
 * leaving each command its own indentation and framing.
 */
final class DependencyTreeRenderer
{
    /**
     * @param list<DependencyTreeNode> $nodes
     *
     * @return list<string>
     */
    public function render(array $nodes, string $indent = ''): array
    {
        return $this->branch($nodes, $indent, '');
    }

    /**
     * @param list<DependencyTreeNode> $nodes
     *
     * @return list<string>
     */
    private function branch(array $nodes, string $indent, string $prefix): array
    {
        $lines = [];
        $lastIndex = count($nodes) - 1;

        foreach ($nodes as $index => $node) {
            $isLast = $index === $lastIndex;

            $lines[] = $indent . $prefix . ($isLast ? '└── ' : '├── ') . $this->label($node);
            $lines = array_merge(
                $lines,
                $this->branch($node->children, $indent, $prefix . ($isLast ? '    ' : '│   ')),
            );
        }

        return $lines;
    }

    /**
     * A cut cycle is called out: a branch that simply stopped reads like one
     * with nothing more to say.
     */
    private function label(DependencyTreeNode $node): string
    {
        $marker = $node->isProvided() ? '<fg=green>✓</>' : '<fg=red>✗</>';
        $parameter = $node->parameter === null ? '' : sprintf('$%s: ', $node->parameter);
        $cycle = $node->repeated ? ' <fg=yellow>(cycle)</>' : '';

        return sprintf('%s %s%s (%s)%s', $marker, $parameter, $node->className, $node->status->value, $cycle);
    }
}
