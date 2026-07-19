<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use Gacela\Console\Domain\AllAppModules\AppModule;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function is_string;
use function preg_match_all;
use function sort;
use function str_starts_with;

final class ModuleGraphBuilder
{
    /**
     * Build the module dependency graph: which module's code declares
     * `use` imports pointing into which other module.
     *
     * @param list<AppModule> $modules
     *
     * @return array<string, list<string>> module namespace => sorted list of module namespaces it depends on
     */
    public function build(array $modules): array
    {
        $graph = [];

        foreach ($modules as $module) {
            $graph[$module->fullModuleName()] = $this->dependenciesOf($module, $modules);
        }

        return $graph;
    }

    /**
     * @param list<AppModule> $allModules
     *
     * @return list<string>
     */
    private function dependenciesOf(AppModule $module, array $allModules): array
    {
        $dependencies = [];

        foreach ($this->moduleImports($module) as $import) {
            foreach ($allModules as $candidate) {
                if ($candidate->fullModuleName() === $module->fullModuleName()) {
                    continue;
                }

                if (str_starts_with($import, $candidate->fullModuleName() . '\\')) {
                    $dependencies[$candidate->fullModuleName()] = $candidate->fullModuleName();
                }
            }
        }

        $list = array_values($dependencies);
        sort($list);

        return $list;
    }

    /**
     * All `use` imports declared across the module's php files.
     *
     * @return list<string>
     */
    private function moduleImports(AppModule $module): array
    {
        $moduleDir = $this->moduleDirectory($module);
        if ($moduleDir === null) {
            return [];
        }

        $imports = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDir, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($fileInfo->getPathname());
            if (!is_string($contents)) {
                continue;
            }

            preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)/m', $contents, $matches);
            foreach ($matches[1] as $import) {
                $imports[] = $import;
            }
        }

        return $imports;
    }

    private function moduleDirectory(AppModule $module): ?string
    {
        $facadeFile = (new ReflectionClass($module->facadeClass()))->getFileName();

        return is_string($facadeFile) ? dirname($facadeFile) : null;
    }
}
