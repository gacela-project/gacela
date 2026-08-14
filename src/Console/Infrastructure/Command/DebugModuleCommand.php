<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\Debug\DependencyTreeInspector;
use Gacela\Console\Application\Debug\DependencyTreeRenderer;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Attribute\ProvidesScanner;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Output\OutputInterface;

use function class_exists;
use function json_encode;

use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class DebugModuleCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const NOT_FOUND = '(not found)';

    protected function configure(): void
    {
        $this->setName('debug:module')
            ->setDescription('Inspect a module: resolved gacela classes, container bindings, and dependency tree')
            ->addArgument('module', InputArgument::REQUIRED, 'Module name (or a part of it)')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output machine-readable JSON')
            ->addOption('tree', 't', InputOption::VALUE_NONE, 'Only print the dependency tree');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $moduleName = ConsoleInput::argument($input, 'module');
        $modules = $this->getFacade()->findAllAppModules($moduleName);

        if ($modules === []) {
            $output->writeln(sprintf('<comment>No module matches "%s".</comment>', $moduleName));

            return Command::FAILURE;
        }

        if ($input->getOption('json') === true) {
            return $this->renderJson($output, $modules);
        }

        $treeOnly = $input->getOption('tree') === true;
        foreach ($modules as $module) {
            $this->renderModule($output, $module, $treeOnly);
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<AppModule> $modules
     */
    private function renderJson(OutputInterface $output, array $modules): int
    {
        $bindings = $this->getFacade()->getContainerBindings();
        $contextualBindings = $this->getFacade()->getContainerContextualBindings();

        $payload = [];
        foreach ($modules as $module) {
            $payload[] = [
                'module' => $module->moduleName(),
                'fullModuleName' => $module->fullModuleName(),
                'facade' => $module->facadeClass(),
                'factory' => $module->factoryClass(),
                'config' => $module->configClass(),
                'provider' => $module->providerClass(),
                'provides' => $this->providedIds($module),
                'bindings' => $bindings,
                'contextualBindings' => $contextualBindings,
                'dependencyTree' => $this->getFacade()->getContainerDependencyTree($module->facadeClass()),
            ];
        }

        $output->writeln(json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ));

        return Command::SUCCESS;
    }

    private function renderModule(OutputInterface $output, AppModule $module, bool $treeOnly): void
    {
        $output->writeln(sprintf('<info>Module: %s</info>', $module->moduleName()));

        if (!$treeOnly) {
            $this->renderResolvedClasses($output, $module);
            $this->renderProvides($output, $module);
            $this->renderBindings($output);
        }

        $this->renderDependencyTree($output, $module);
        $output->writeln('');
    }

    private function renderResolvedClasses(OutputInterface $output, AppModule $module): void
    {
        $output->writeln(sprintf('  <fg=cyan>Facade</>    → %s', $module->facadeClass()));
        $output->writeln(sprintf('  <fg=cyan>Factory</>   → %s', $module->factoryClass() ?? self::NOT_FOUND));
        $output->writeln(sprintf('  <fg=cyan>Config</>    → %s', $module->configClass() ?? self::NOT_FOUND));
        $output->writeln(sprintf('  <fg=cyan>Provider</>  → %s', $module->providerClass() ?? self::NOT_FOUND));
    }

    /**
     * The ids this module's own Provider declares, which is the question
     * `getProvidedDependency('...')` asks and the one nothing here answered:
     * the Provider was listed as a pillar and its whole purpose left blank,
     * while `Bindings` below reports the *application's* container, identical
     * for every module printed.
     *
     * Attribute-declared ids only, and labelled as such. A Provider may also
     * `set()` ids imperatively inside `provideModuleDependencies()`, and
     * finding those means running it against a container -- which resolves the
     * services, for a command whose job is to describe rather than to build.
     */
    private function renderProvides(OutputInterface $output, AppModule $module): void
    {
        $output->writeln('  <fg=cyan>Provides</> (#[Provides]):');

        $ids = $this->providedIds($module);
        if ($ids === []) {
            $output->writeln('    (none)');

            return;
        }

        foreach ($ids as $id) {
            $output->writeln(sprintf('    %s', $id));
        }
    }

    /**
     * @return list<string>
     */
    private function providedIds(AppModule $module): array
    {
        $providerClass = $module->providerClass();
        if ($providerClass === null || !class_exists($providerClass)) {
            return [];
        }

        $ids = [];
        foreach (ProvidesScanner::entriesFor(new ReflectionClass($providerClass)) as $entry) {
            $ids[] = $entry['id'];
        }

        sort($ids);

        return $ids;
    }

    private function renderBindings(OutputInterface $output): void
    {
        $bindings = $this->getFacade()->getContainerBindings();
        $contextualBindings = $this->getFacade()->getContainerContextualBindings();

        $output->writeln('  <fg=cyan>Bindings</>:');

        if ($bindings === [] && $contextualBindings === []) {
            $output->writeln('    (none)');

            return;
        }

        foreach ($bindings as $abstract => $concrete) {
            $output->writeln(sprintf('    %s => %s', $abstract, $concrete));
        }

        foreach ($contextualBindings as $consumer => $needs) {
            foreach ($needs as $abstract => $concrete) {
                $output->writeln(sprintf('    %s (contextual for %s) => %s', $abstract, $consumer, $concrete));
            }
        }
    }

    private function renderDependencyTree(OutputInterface $output, AppModule $module): void
    {
        $output->writeln('  <fg=cyan>Dependency tree</> (Facade):');

        $inspection = (new DependencyTreeInspector())->inspect($module->facadeClass());

        if ($inspection->tree === []) {
            $output->writeln('    (no dependencies)');

            return;
        }

        foreach ((new DependencyTreeRenderer())->render($inspection->tree, '    ') as $line) {
            $output->writeln($line);
        }
    }
}
