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

/**
 * @psalm-type ImportEvidence = array{file: string, line: int, import: string}
 */
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
     * Where one edge of the graph was written: every import in the module that
     * points into `$dependencyNamespace`, with the file and the line declaring
     * it.
     *
     * The graph answers whether a dependency exists; a reader who has just been
     * told it does needs the line to go and look at. Both answers come from the
     * same parse and the same namespace matching, so evidence cannot name an
     * edge the graph does not report, nor go missing for one it does.
     *
     * @return list<ImportEvidence> in the order the module's files are walked,
     *                              and within a file in declaration order
     */
    public function importsPointingInto(AppModule $module, string $dependencyNamespace): array
    {
        $ownName = $module->fullModuleName();
        if ($dependencyNamespace === $ownName) {
            return [];
        }

        $moduleDir = $this->moduleDirectory($module);
        if ($moduleDir === null) {
            return [];
        }

        $owner = [$dependencyNamespace => true];
        $evidence = [];

        foreach ($this->phpSourcesIn($moduleDir) as $file => $source) {
            foreach ($this->importParser->importsWithLinesIn($source) as $import) {
                if ($this->owningModulesOf($import['name'], $owner) === []) {
                    continue;
                }

                $evidence[] = ['file' => $file, 'line' => $import['line'], 'import' => $import['name']];
            }
        }

        return $evidence;
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

        sort($dependencies);

        return $dependencies;
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
        foreach ($this->phpSourcesIn($moduleDir) as $source) {
            foreach ($this->importParser->importsIn($source) as $import) {
                $imports[] = $import;
            }
        }

        return $imports;
    }

    /**
     * The contents of every php file under a directory, keyed by its path.
     *
     * Separated so the method above is about imports rather than about
     * traversal: which files are read is one question, what is read out of them
     * is another.
     *
     * @return iterable<string, string>
     */
    private function phpSourcesIn(string $directory): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $path = $fileInfo->getPathname();
            $contents = file_get_contents($path);
            if (is_string($contents)) {
                yield $path => $contents;
            }
        }
    }

    private function moduleDirectory(AppModule $module): ?string
    {
        $facadeFile = (new ReflectionClass($module->facadeClass()))->getFileName();

        return is_string($facadeFile) ? dirname($facadeFile) : null;
    }
}
