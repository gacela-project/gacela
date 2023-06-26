<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\DocBlockResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method ConsoleFacade getFacade()
 */
final class ListModulesCommand extends Command
{
    use DocBlockResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('list:modules')
            ->setDescription('Render all modules found')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Any filter to simplify the output')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Display all the modules in detail');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = (string)$input->getArgument('filter');

        $this->generateListOfModules(
            $output,
            (bool)$input->getOption('detailed'),
            $this->getFacade()->findAllAppModules($filter),
        );

        return self::SUCCESS;
    }

    /**
     * @param list<AppModule> $modules
     */
    private function generateListOfModules(OutputInterface $output, bool $isDetailed, array $modules): void
    {
        if ($isDetailed) {
            $this->generateDetailedView($output, $modules);
            return;
        }

        $this->generateSimpleView($output, $modules);
    }

    /**
     * @param list<AppModule> $modules
     */
    private function generateSimpleView(OutputInterface $output, array $modules): void
    {
        $rows = [];

        foreach ($modules as $module) {
            $rows[] = [
                $module->fullModuleName(),
                '✔️', // facade is always true
                $module->factoryClass() ? '✔️' : '✖️',
                $module->configClass() ? '✔️' : '✖️',
                $module->dependencyProviderClass() ? '✔️' : '✖️',
            ];
        }
        $table = new Table($output);
        $table->setStyle('box');
        $table->setHeaders(['Module namespace', 'Facade', 'Factory', 'Config', 'Dep. Provider']);
        $table->setRows($rows);
        $table->render();
    }

    /**
     * @param list<AppModule> $modules
     */
    private function generateDetailedView(OutputInterface $output, array $modules): void
    {
        $result = '';
        foreach ($modules as $i => $module) {
            $n = $i + 1;
            $factory = $module->factoryClass() ?? 'None';
            $config = $module->configClass() ?? 'None';
            $dependencyProviderClass = $module->dependencyProviderClass() ?? 'None';

            $result .= <<<TXT
============================
{$n}.- <fg=green>{$module->moduleName()}</>
----------------------------
<fg=cyan>Facade</>: {$module->facadeClass()}
<fg=cyan>Factory</>: {$factory}
<fg=cyan>Config</>: {$config}
<fg=cyan>DependencyProvider</>: {$dependencyProviderClass}

TXT;
        }

        $output->write($result);
    }
}
