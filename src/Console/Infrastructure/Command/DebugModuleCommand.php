<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * @method ConsoleFacade getFacade()
 */
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
        $moduleName = (string)$input->getArgument('module');
        $modules = $this->getFacade()->findAllAppModules($moduleName);

        if ($modules === []) {
            $output->writeln(sprintf('<comment>No module matches "%s".</comment>', $moduleName));

            return Command::FAILURE;
        }

        if ((bool)$input->getOption('json')) {
            return $this->renderJson($output, $modules);
        }

        $treeOnly = (bool)$input->getOption('tree');
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

        $dependencyTree = $this->getFacade()->getContainerDependencyTree($module->facadeClass());

        if ($dependencyTree === []) {
            $output->writeln('    (no dependencies)');

            return;
        }

        foreach ($dependencyTree as $dependency) {
            $output->writeln('    ' . $dependency);
        }
    }
}
