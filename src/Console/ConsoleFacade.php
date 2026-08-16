<?php

declare(strict_types=1);

namespace Gacela\Console;

use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Console\Domain\AllAppModules\UndiscoveredFacadeFile;
use Gacela\Console\Domain\CommandArguments\CommandArguments;
use Gacela\Console\Domain\DtoGenerate\DtoGenerateResult;
use Gacela\Console\Domain\FileContent\StubPublishResult;
use Gacela\Console\Domain\IdeMeta\IdeMetadataResult;
use Gacela\Console\Domain\ModuleGraph\GraphDiffResult;
use Gacela\Console\Domain\ModuleGraph\ModuleRuleCheckResult;
use Gacela\Console\Domain\ServiceMapMigration\MigrationResult;
use Gacela\Container\ContainerStats;
use Gacela\Framework\AbstractFacade;
use Gacela\StaticAnalysis\ModuleRules\ModuleRuleSet;

/**
 * @extends AbstractFacade<ConsoleFactory>
 */
final class ConsoleFacade extends AbstractFacade
{
    public function sanitizeFilename(string $filename): string
    {
        return $this->getFactory()
            ->createFilenameSanitizer()
            ->sanitize($filename);
    }

    public function parseArguments(string $desiredNamespace): CommandArguments
    {
        return $this->getFactory()
            ->createCommandArgumentsParser()
            ->parse($desiredNamespace);
    }

    public function generateFileContent(
        CommandArguments $commandArguments,
        string $filename,
        bool $withShortName = false,
    ): string {
        return $this->getFactory()
            ->createFileContentGenerator()
            ->generate($commandArguments, $filename, $withShortName);
    }

    /**
     * Which of the files a `make:*` run would write already exist.
     *
     * A target path is built from the module arguments alone -- the stubs a
     * template would fill in never reach it -- so which template is in play
     * makes no difference here, and asking would be a distinction nothing
     * could observe.
     *
     * @param list<array{string, string}> $files [filename, subDirectory] pairs
     *
     * @return list<string>
     */
    public function existingGeneratedFiles(
        CommandArguments $commandArguments,
        array $files,
        bool $withShortName,
    ): array {
        return $this->getFactory()
            ->createFileContentGenerator()
            ->existingTargets($commandArguments, $files, $withShortName);
    }

    /**
     * Every file a `make:*` run would write, and whether each is already there.
     *
     * What `--dry-run` reports, resolved through the same path logic generation
     * uses -- a preview that could disagree with the run it previews would be
     * worse than none.
     *
     * @param list<array{string, string}> $files [filename, subDirectory] pairs
     *
     * @return list<array{path: string, exists: bool}>
     */
    public function plannedGeneratedFiles(
        CommandArguments $commandArguments,
        array $files,
        bool $withShortName,
    ): array {
        return $this->getFactory()
            ->createFileContentGenerator()
            ->plannedTargets($commandArguments, $files, $withShortName);
    }

    /**
     * Generate a file from the `service` template set.
     *
     * @param string $subDirectory optional sub-directory (relative to the module dir) to place the file in
     */
    public function generateServiceFileContent(
        CommandArguments $commandArguments,
        string $filename,
        bool $withShortName = false,
        string $subDirectory = '',
    ): string {
        return $this->getFactory()
            ->createServiceFileContentGenerator()
            ->generate($commandArguments, $filename, $withShortName, $subDirectory);
    }

    /**
     * @return list<AppModule>
     */
    public function findAllAppModules(string $filter = ''): array
    {
        return $this->getFactory()
            ->createAllAppModulesFinder()
            ->findAllAppModules($filter);
    }

    /**
     * Writes the `#[ServiceMap]` attribute the static analysis reports as
     * missing, across every scanned file.
     *
     * The last rung of the 3.0 migration ladder: deprecate, detect, suggest --
     * and then apply, which users were doing by hand from a suggestion the
     * tooling had already computed.
     *
     * @return list<MigrationResult> only the files that changed
     */
    public function migrateServiceMaps(string $filter = '', bool $dryRun = false): array
    {
        return $this->getFactory()
            ->createServiceMapMigrationRunner()
            ->run($filter, $dryRun);
    }

    /**
     * The `appModulePaths` entries discovery walked, for a report that found
     * nothing and has to say where it looked.
     *
     * @return list<string>
     */
    public function scannedModulePaths(): array
    {
        return $this->getFactory()->scannedModulePaths();
    }

    /**
     * The configured `appModulePaths` entries discovery skipped, because they
     * name something that is not a directory.
     *
     * @return list<string>
     */
    public function unscannedModulePaths(): array
    {
        return $this->getFactory()->unscannedModulePaths();
    }

    /**
     * Files named like a Facade that discovery did not turn into a module.
     *
     * The inverse of findAllAppModules(): every check works from the modules
     * that were found, so one that was never found is invisible to all of them.
     *
     * @return list<UndiscoveredFacadeFile>
     */
    public function findUndiscoveredFacadeFiles(): array
    {
        return $this->getFactory()
            ->createUndiscoveredFacadeFinder()
            ->find();
    }

    /**
     * Generate the declared DTO classes, or, with $dryRun, work out what would
     * be written and write nothing.
     */
    public function generateDtoClasses(bool $dryRun): DtoGenerateResult
    {
        return $this->getFactory()
            ->createDtoGenerator()
            ->generate($this->getFactory()->createDtoSchema(), $dryRun);
    }

    /**
     * Write the editor metadata for `getProvidedDependency()`, or, with
     * $dryRun, work out what it would say and write nothing.
     */
    public function generateIdeMetadata(bool $dryRun): IdeMetadataResult
    {
        return $this->getFactory()
            ->createIdeMetadataGenerator()
            ->generate($dryRun);
    }

    /**
     * Build the module dependency graph: module namespace => the module
     * namespaces its `use` imports point into.
     *
     * @return array<string, list<string>>
     */
    public function buildModuleGraph(string $filter = ''): array
    {
        return $this->getFactory()
            ->createModuleGraphBuilder()
            ->build($this->findAllAppModules($filter));
    }

    /**
     * @param array<string, list<string>> $graph
     */
    public function formatModuleGraph(array $graph, string $format): string
    {
        return $this->getFactory()
            ->createModuleGraphFormatter($format)
            ->format($graph);
    }

    /**
     * Every dependency cycle in the module graph, each as a sorted module list.
     *
     * @param array<string, list<string>> $graph
     *
     * @return list<list<string>>
     */
    public function detectModuleCycles(array $graph): array
    {
        return $this->getFactory()
            ->createModuleCycleDetector()
            ->detect($graph);
    }

    /**
     * Read the declared module rules against the graph the project has: the
     * dependencies they forbid, and the rules that govern nothing any more.
     *
     * @param array<string, list<string>> $graph
     */
    public function checkModuleRules(array $graph, ModuleRuleSet $rules): ModuleRuleCheckResult
    {
        return $this->getFactory()
            ->createModuleRuleChecker()
            ->check($graph, $rules);
    }

    /**
     * Compare a previously captured module graph against another one.
     *
     * @param array<string, list<string>> $base
     * @param array<string, list<string>> $head
     */
    public function diffModuleGraph(array $base, array $head): GraphDiffResult
    {
        return $this->getFactory()
            ->createModuleGraphDiffer()
            ->diff($base, $head);
    }

    /**
     * Render a graph diff as markdown, with a mermaid block GitHub renders natively.
     *
     * @param array<string, list<string>> $head
     */
    public function formatModuleGraphDiff(GraphDiffResult $diff, array $head): string
    {
        return $this->getFactory()
            ->createGraphDiffMarkdownFormatter()
            ->format($diff, $head);
    }

    /**
     * Where a project's published scaffolder stubs live, absolute.
     */
    public function stubsDir(): string
    {
        return $this->getFactory()->stubsDir();
    }

    /**
     * Copy the built-in stubs into the project.
     *
     * @param list<string> $only the stub files to publish; every one when empty
     */
    public function publishStubs(
        string $stubsDir,
        array $only = [],
        bool $force = false,
        bool $dryRun = false,
    ): StubPublishResult {
        return $this->getFactory()
            ->createStubPublisher()
            ->publish($stubsDir, $only, $force, $dryRun);
    }

    public function getContainerStats(): ContainerStats
    {
        return $this->getFactory()->getContainerStats();
    }

    /**
     * @param class-string $className
     *
     * @return list<string>
     */
    public function getContainerDependencyTree(string $className): array
    {
        return $this->getFactory()->getContainerDependencyTree($className);
    }

    /**
     * @return array<string,string>
     */
    public function getContainerBindings(): array
    {
        return $this->getFactory()->getContainerBindings();
    }

    /**
     * @return array<string,array<string,string>>
     */
    public function getContainerContextualBindings(): array
    {
        return $this->getFactory()->getContainerContextualBindings();
    }
}
