<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use function array_diff;
use function array_keys;
use function array_values;
use function sort;

final class ModuleGraphDiffer
{
    /**
     * Compare two module dependency graphs, as produced by ModuleGraphBuilder.
     *
     * Edges belonging to an added module are reported both as an added module
     * and as added edges: reviewers care about the new module *and* about what
     * it reaches into, and the second is not derivable from the first.
     *
     * @param array<string, list<string>> $base
     * @param array<string, list<string>> $head
     */
    public function diff(array $base, array $head): GraphDiffResult
    {
        return new GraphDiffResult(
            $this->modulesOnlyIn($head, $base),
            $this->modulesOnlyIn($base, $head),
            $this->edgesOnlyIn($head, $base),
            $this->edgesOnlyIn($base, $head),
        );
    }

    /**
     * @param array<string, list<string>> $subject
     * @param array<string, list<string>> $other
     *
     * @return list<string>
     */
    private function modulesOnlyIn(array $subject, array $other): array
    {
        $modules = array_values(array_diff(array_keys($subject), array_keys($other)));
        sort($modules);

        return $modules;
    }

    /**
     * @param array<string, list<string>> $subject
     * @param array<string, list<string>> $other
     *
     * @return list<array{from: string, to: string}>
     */
    private function edgesOnlyIn(array $subject, array $other): array
    {
        $edges = [];

        foreach ($subject as $module => $dependencies) {
            foreach (array_diff($dependencies, $other[$module] ?? []) as $dependency) {
                $edges[] = ['from' => $module, 'to' => $dependency];
            }
        }

        return $edges;
    }
}
