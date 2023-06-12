<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\DocBlockResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
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
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Display a detailed information of each module');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = (string)$input->getArgument('filter');
        $isDetailed = (bool)$input->getOption('detailed');
        $modules = $this->getFacade()->findAllAppModules($filter);

        if ($isDetailed) {
            $return = $this->generateDetailedView($modules);
        } else {
            $return = $this->generateNonDetailedView($modules);
        }

        $output->write($return);

        return self::SUCCESS;
    }

    /**
     * @param list<AppModule> $modules
     */
    private function generateDetailedView(array $modules): string
    {
        $result = '';
        foreach ($modules as $module) {
            $factory = $module->factoryClass() ?? 'None';
            $config = $module->configClass() ?? 'None';
            $dependencyProviderClass = $module->dependencyProviderClass() ?? 'None';

            $result .= <<<TXT
==============
{$module->moduleName()}
--------------
Facade: {$module->facadeClass()}
Factory: {$factory}
Config: {$config}
DependencyProvider: {$dependencyProviderClass}

TXT;
        }
        return $result;
    }

    /**
     * @param list<AppModule> $modules
     */
    private function generateNonDetailedView(array $modules): string
    {
        $result = '';

        foreach ($modules as $module) {
            $result .= <<<TXT
==============
{$module->moduleName()}

TXT;
        }

        return $result;
    }
}
