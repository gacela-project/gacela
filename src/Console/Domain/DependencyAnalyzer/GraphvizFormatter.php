<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\DependencyAnalyzer;

final class GraphvizFormatter implements DependencyFormatterInterface
{
    /**
     * @param list<TModuleDependency> $dependencies
     */
    public function format(array $dependencies): string
    {
        $output = "digraph ModuleDependencies {\n";
        $output .= "    rankdir=LR;\n";
        $output .= "    node [shape=box, style=rounded];\n\n";

        foreach ($dependencies as $dep) {
            $moduleId = $this->sanitizeId($dep->moduleName());

            if (!$dep->hasDependencies()) {
                $output .= "    \"{$moduleId}\" [label=\"{$dep->moduleName()}\"];\n";
                continue;
            }

            foreach ($dep->dependencies() as $dependency) {
                $depId = $this->sanitizeId($dependency);
                $output .= "    \"{$moduleId}\" -> \"{$depId}\";\n";
            }
        }

        return $output . "}\n";
    }

    private function sanitizeId(string $name): string
    {
        return str_replace(['\\'], '\\\\', $name);
    }
}
