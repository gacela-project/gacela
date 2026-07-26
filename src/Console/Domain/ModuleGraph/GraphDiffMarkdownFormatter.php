<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use function array_values;
use function implode;
use function in_array;
use function sprintf;
use function str_replace;

/**
 * Renders a graph diff as GitHub-flavoured markdown: a summary of what moved,
 * followed by a mermaid block GitHub renders natively in a PR comment.
 *
 * @psalm-import-type GraphEdge from GraphDiffResult
 */
final class GraphDiffMarkdownFormatter
{
    public const MARKER = '<!-- gacela-module-graph -->';

    /**
     * @param array<string, list<string>> $head the graph as it looks now
     */
    public function format(GraphDiffResult $diff, array $head): string
    {
        $lines = [
            self::MARKER,
            '### Module dependency graph changed',
            '',
        ];

        foreach ($this->summarySections($diff) as $section) {
            $lines[] = $section;
        }

        $lines[] = '```mermaid';
        $lines[] = $this->mermaid($diff, $head);
        $lines[] = '```';
        $lines[] = '';
        $lines[] = 'Legend: `==>` new dependency · `-.->` removed dependency · `-->` unchanged.';
        $lines[] = 'Only the modules this change touches are drawn; the full graph is in the `module-graph` artifact.';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return list<string>
     */
    private function summarySections(GraphDiffResult $diff): array
    {
        $sections = [];

        foreach ($diff->addedEdges as $edge) {
            $sections[] = sprintf('- **new dependency** `%s` → `%s`', $edge['from'], $edge['to']);
        }

        foreach ($diff->removedEdges as $edge) {
            $sections[] = sprintf('- **removed dependency** `%s` → `%s`', $edge['from'], $edge['to']);
        }

        foreach ($diff->addedModules as $module) {
            $sections[] = sprintf('- **new module** `%s`', $module);
        }

        foreach ($diff->removedModules as $module) {
            $sections[] = sprintf('- **removed module** `%s`', $module);
        }

        $sections[] = '';

        return $sections;
    }

    /**
     * Draws only the part of the graph the diff touches.
     *
     * Rendering the whole graph would be honest and useless: a mid-sized app
     * puts dozens of unchanged nodes in front of the two that moved, and the
     * reader stops looking. The full graph ships as the JSON artifact instead.
     *
     * @param array<string, list<string>> $head
     */
    private function mermaid(GraphDiffResult $diff, array $head): string
    {
        $affected = $this->affectedModules($diff);
        $edges = [];
        $connected = [];

        foreach ($head as $module => $dependencies) {
            foreach ($dependencies as $dependency) {
                if (!in_array($module, $affected, true) || !in_array($dependency, $affected, true)) {
                    continue;
                }

                $arrow = in_array(['from' => $module, 'to' => $dependency], $diff->addedEdges, true) ? '==>' : '-->';
                $edges[] = sprintf('    %s %s %s', self::nodeId($module), $arrow, self::nodeId($dependency));
                $connected[] = $module;
                $connected[] = $dependency;
            }
        }

        // Removed edges are gone from the head graph, so they have to be drawn
        // back in — a dependency that disappeared is the half of the diff a
        // reviewer cannot see anywhere else.
        foreach ($diff->removedEdges as $edge) {
            $edges[] = sprintf('    %s -.-> %s', self::nodeId($edge['from']), self::nodeId($edge['to']));
            $connected[] = $edge['from'];
            $connected[] = $edge['to'];
        }

        // A module whose only change is its own existence has no edge to hang
        // on, and mermaid only renders what a line mentions.
        $isolated = [];
        foreach ($affected as $module) {
            if (!in_array($module, $connected, true)) {
                $isolated[] = sprintf('    %s', self::nodeId($module));
            }
        }

        return implode("\n", ['graph TD', ...$edges, ...$isolated]);
    }

    /**
     * Every module the diff mentions, directly or as an edge endpoint.
     *
     * @return list<string>
     */
    private function affectedModules(GraphDiffResult $diff): array
    {
        // Keyed by name so a module named twice -- added *and* an edge endpoint
        // -- collapses on the way in, rather than being deduplicated afterwards.
        $modules = [];

        foreach ([...$diff->addedModules, ...$diff->removedModules] as $module) {
            $modules[$module] = $module;
        }

        foreach ([...$diff->addedEdges, ...$diff->removedEdges] as $edge) {
            $modules[$edge['from']] = $edge['from'];
            $modules[$edge['to']] = $edge['to'];
        }

        return array_values($modules);
    }

    private static function nodeId(string $module): string
    {
        return str_replace('\\', '.', $module);
    }
}
