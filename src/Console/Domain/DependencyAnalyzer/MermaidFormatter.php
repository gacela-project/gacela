<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\DependencyAnalyzer;

final class MermaidFormatter implements DependencyFormatterInterface
{
    /**
     * @param list<TModuleDependency> $dependencies
     */
    public function format(array $dependencies): string
    {
        $output = "graph TD\n";

        foreach ($dependencies as $dep) {
            $moduleId = $this->sanitizeId($dep->moduleName());

            if (!$dep->hasDependencies()) {
                $output .= "    {$moduleId}[{$dep->moduleName()}]\n";
                continue;
            }

            foreach ($dep->dependencies() as $dependency) {
                $depId = $this->sanitizeId($dependency);
                $output .= "    {$moduleId}[{$dep->moduleName()}] --> {$depId}[{$dependency}]\n";
            }
        }

        return $output;
    }

    private function sanitizeId(string $name): string
    {
        return str_replace(['\\', '/', ' ', '.'], '_', $name);
    }
}
