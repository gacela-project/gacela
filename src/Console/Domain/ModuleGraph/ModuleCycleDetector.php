<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use function array_keys;
use function array_pop;
use function count;
use function in_array;
use function min;
use function sort;
use function usort;

final class ModuleCycleDetector
{
    /**
     * Every dependency cycle in the module graph, each as a sorted module list.
     *
     * A cycle is a strongly-connected component of more than one module, or a
     * module that imports itself. Tarjan reports *every* component in one pass,
     * so a graph with several independent cycles does not hide all but the first.
     *
     * @param array<string, list<string>> $graph
     *
     * @return list<list<string>>
     */
    public function detect(array $graph): array
    {
        $cycles = [];

        foreach ($this->stronglyConnectedComponents($graph) as $component) {
            if (count($component) === 1 && !in_array($component[0], $graph[$component[0]] ?? [], true)) {
                continue;
            }

            sort($component);
            $cycles[] = $component;
        }

        usort($cycles, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $cycles;
    }

    /**
     * Tarjan's strongly-connected-components algorithm, iterative so that a deep
     * module graph cannot exhaust the PHP stack.
     *
     * @param array<string, list<string>> $edges
     *
     * @return list<list<string>>
     */
    private function stronglyConnectedComponents(array $edges): array
    {
        $index = [];
        $low = [];
        $onStack = [];
        $stack = [];
        $result = [];
        $counter = 0;

        foreach (array_keys($edges) as $start) {
            if (isset($index[$start])) {
                continue;
            }

            // Each frame tracks the node and how far through its edges we are.
            $work = [[$start, 0]];

            while ($work !== []) {
                [$node, $edgePointer] = $work[count($work) - 1];

                if ($edgePointer === 0) {
                    $index[$node] = $counter;
                    $low[$node] = $counter;
                    ++$counter;
                    $stack[] = $node;
                    $onStack[$node] = true;
                }

                $recursed = false;
                $neighbors = $edges[$node] ?? [];
                for ($i = $edgePointer; $i < count($neighbors); ++$i) {
                    $next = $neighbors[$i];
                    if (!isset($edges[$next])) {
                        continue;
                    }

                    if (!isset($index[$next])) {
                        $work[count($work) - 1] = [$node, $i + 1];
                        $work[] = [$next, 0];
                        $recursed = true;
                        break;
                    }

                    // No `?? false` default: reaching here means $next has an
                    // $index, and $index and $onStack are written together.
                    if ($onStack[$next] === true) {
                        $low[$node] = min($low[$node], $index[$next]);
                    }
                }

                if ($recursed) {
                    continue;
                }

                if ($low[$node] === $index[$node]) {
                    $component = [];

                    // Unwinds to $node, which is always still on the stack; the
                    // emptiness guard is what lets the popped value be a string
                    // rather than string|null.
                    while ($stack !== []) {
                        $popped = array_pop($stack);
                        $onStack[$popped] = false;
                        $component[] = $popped;

                        if ($popped === $node) {
                            break;
                        }
                    }

                    $result[] = $component;
                }

                array_pop($work);
                if ($work !== []) {
                    $parent = $work[count($work) - 1][0];
                    $low[$parent] = min($low[$parent], $low[$node]);
                }
            }
        }

        return $result;
    }
}
