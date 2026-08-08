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
use function sort;

final class ModuleGraphBuilder
{
    public function __construct(
        private readonly PhpImportParser $importParser = new PhpImportParser(),
    ) {
    }

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
        // Indexed by name, so an import is resolved by looking up its own
        // namespace segments instead of being compared against every module.
        $moduleNames = [];
        foreach ($modules as $module) {
            $moduleNames[$module->fullModuleName()] = true;
        }

        $graph = [];
        foreach ($modules as $module) {
            $graph[$module->fullModuleName()] = $this->dependenciesOf($module, $moduleNames);
        }

        return $graph;
    }

    /**
     * @param array<string, true> $moduleNames
     *
     * @return list<string>
     */
    private function dependenciesOf(AppModule $module, array $moduleNames): array
    {
        $ownName = $module->fullModuleName();
        $dependencies = [];

        foreach ($this->moduleImports($module) as $import) {
            foreach ($this->owningModulesOf($import, $moduleNames) as $owner) {
                if ($owner !== $ownName) {
                    $dependencies[$owner] = $owner;
                }
            }
        }

        // sort() discards keys and reindexes; array_values() first would be a no-op.
        $list = $dependencies;
        sort($list);

        return $list;
    }

    /**
     * Every module namespace that is a prefix of the import.
     *
     * Walking the import's own segments costs its depth; comparing each import
     * against every module made graph construction grow as imports × modules.
     * All matching ancestors are returned, not just the closest, because a
     * module nested inside another is a dependency on both.
     *
     * @param array<string, true> $moduleNames
     *
     * @return list<string>
     */
    private function owningModulesOf(string $import, array $moduleNames): array
    {
        $segments = explode('\\', $import);
        // The class name itself is never a module namespace.
        array_pop($segments);

        $owners = [];
        while ($segments !== []) {
            $candidate = implode('\\', $segments);
            if (isset($moduleNames[$candidate])) {
                $owners[] = $candidate;
            }

            array_pop($segments);
        }

        return $owners;
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
            if (!$fileInfo->isFile()) {
                continue;
            }

            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($fileInfo->getPathname());
            if (!is_string($contents)) {
                continue;
            }

            foreach ($this->importParser->importsIn($contents) as $import) {
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
