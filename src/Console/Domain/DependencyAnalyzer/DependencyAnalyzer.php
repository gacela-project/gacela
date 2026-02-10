<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\DependencyAnalyzer;

use Gacela\Console\Domain\AllAppModules\AppModule;
use ReflectionClass;

use Throwable;

use function count;
use function in_array;

final class DependencyAnalyzer
{
    /**
     * @param list<AppModule> $modules
     *
     * @return list<TModuleDependency>
     */
    public function analyzeModules(array $modules): array
    {
        $dependencies = [];
        $moduleMap = $this->buildModuleMap($modules);

        foreach ($modules as $module) {
            try {
                $deps = $this->extractModuleDependencies($module, $moduleMap);
                $depth = $this->calculateDepth($module, $moduleMap, []);
            } catch (Throwable) {
                $deps = [];
                $depth = 0;
            }

            $dependencies[] = new TModuleDependency(
                $module->fullModuleName(),
                $deps,
                $depth,
            );
        }

        return $dependencies;
    }

    /**
     * @param list<TModuleDependency> $dependencies
     *
     * @return list<array{from: string, to: string}>
     */
    public function detectCircularDependencies(array $dependencies): array
    {
        $circular = [];
        $visited = [];
        $stack = [];

        $dependencyMap = [];
        foreach ($dependencies as $dep) {
            $dependencyMap[$dep->moduleName()] = $dep->dependencies();
        }

        foreach ($dependencies as $dep) {
            $this->detectCircularDependenciesRecursive(
                $dep->moduleName(),
                $dependencyMap,
                $visited,
                $stack,
                $circular,
            );
        }

        return $circular;
    }

    /**
     * @param list<AppModule> $modules
     *
     * @return array<string, AppModule>
     */
    private function buildModuleMap(array $modules): array
    {
        $map = [];
        foreach ($modules as $module) {
            $map[$module->fullModuleName()] = $module;
        }

        return $map;
    }

    /**
     * @param array<string, AppModule> $moduleMap
     *
     * @return list<string>
     */
    private function extractModuleDependencies(AppModule $module, array $moduleMap): array
    {
        $dependencies = [];

        try {
            if ($module->factoryClass() !== null && class_exists($module->factoryClass())) {
                $dependencies = array_merge(
                    $dependencies,
                    $this->extractDependenciesFromClass($module->factoryClass(), $moduleMap),
                );
            }
        } catch (Throwable) {
            // Skip modules that can't be analyzed
        }

        try {
            if ($module->providerClass() !== null && class_exists($module->providerClass())) {
                $dependencies = array_merge(
                    $dependencies,
                    $this->extractDependenciesFromClass($module->providerClass(), $moduleMap),
                );
            }
        } catch (Throwable) {
            // Skip modules that can't be analyzed
        }

        return array_values(array_unique($dependencies));
    }

    /**
     * @param class-string $className
     * @param array<string, AppModule> $moduleMap
     *
     * @return list<string>
     */
    private function extractDependenciesFromClass(string $className, array $moduleMap): array
    {
        $dependencies = [];
        $reflection = new ReflectionClass($className);
        $content = (string)file_get_contents((string)$reflection->getFileName());

        foreach ($moduleMap as $moduleName => $module) {
            if ($moduleName === $this->getModuleNameFromClass($className)) {
                continue;
            }

            $facadeClass = $module->facadeClass();
            if (str_contains($content, $facadeClass)) {
                $dependencies[] = $moduleName;
            }
        }

        return $dependencies;
    }

    /**
     * @param class-string $className
     */
    private function getModuleNameFromClass(string $className): string
    {
        $parts = explode('\\', $className);
        array_pop($parts);

        return implode('\\', $parts);
    }

    /**
     * @param array<string, AppModule> $moduleMap
     * @param list<string> $visited
     */
    private function calculateDepth(AppModule $module, array $moduleMap, array $visited): int
    {
        $moduleName = $module->fullModuleName();

        if (in_array($moduleName, $visited, true)) {
            return 0;
        }

        $visited[] = $moduleName;
        $dependencies = $this->extractModuleDependencies($module, $moduleMap);

        if (count($dependencies) === 0) {
            return 0;
        }

        $maxDepth = 0;
        foreach ($dependencies as $depName) {
            if (isset($moduleMap[$depName])) {
                $depth = $this->calculateDepth($moduleMap[$depName], $moduleMap, $visited);
                $maxDepth = max($maxDepth, $depth);
            }
        }

        return $maxDepth + 1;
    }

    /**
     * @param array<string, list<string>> $dependencyMap
     * @param array<string, bool> $visited
     * @param array<string, bool> $stack
     * @param list<array{from: string, to: string}> $circular
     */
    private function detectCircularDependenciesRecursive(
        string $module,
        array $dependencyMap,
        array &$visited,
        array &$stack,
        array &$circular,
    ): void {
        if (isset($stack[$module])) {
            return;
        }

        if (isset($visited[$module])) {
            return;
        }

        $visited[$module] = true;
        $stack[$module] = true;

        $dependencies = $dependencyMap[$module] ?? [];

        foreach ($dependencies as $dependency) {
            if (isset($stack[$dependency])) {
                $circular[] = ['from' => $module, 'to' => $dependency];
            } else {
                $this->detectCircularDependenciesRecursive(
                    $dependency,
                    $dependencyMap,
                    $visited,
                    $stack,
                    $circular,
                );
            }
        }

        unset($stack[$module]);
    }
}
