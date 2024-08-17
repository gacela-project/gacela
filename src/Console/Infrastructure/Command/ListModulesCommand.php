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
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method ConsoleFacade getFacade()
 */
final class ListModulesCommand extends Command
{
    use DocBlockResolverAwareTrait;

    private const CHECK_SYMBOL = '✔️';

    private const CROSS_SYMBOL = '✖️';

    private ?OutputInterface $output = null;

    protected function configure(): void
    {
        $this->setName('list:modules')
            ->setDescription('Render all modules found')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Any filter to simplify the output')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Display all the modules in detail');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $filter = (string)$input->getArgument('filter');

        $this->generateListOfModules(
            (bool)$input->getOption('detailed'),
            $this->getFacade()->findAllAppModules($filter),
        );

        return self::SUCCESS;
    }

    private function output(): OutputInterface
    {
        return $this->output ?? new ConsoleOutput();
    }

    /**
     * @param list<AppModule> $modules
     */
    private function generateListOfModules(bool $isDetailed, array $modules): void
    {
        if ($isDetailed) {
            $this->generateDetailedView($modules);
        } else {
            $this->generateSimpleView($modules);
        }
    }

    /**
     * @param list<AppModule> $modules
     */
    private function generateDetailedView(array $modules): void
    {
        $result = '';
        foreach ($modules as $i => $module) {
            $n = $i + 1;
            $factory = $module->factoryClass() ?? self::CROSS_SYMBOL;
            $config = $module->configClass() ?? self::CROSS_SYMBOL;
            $provider = $module->providerClass() ?? self::CROSS_SYMBOL;

            $result .= <<<TXT
============================
{$n}.- <fg=green>{$module->moduleName()}</>
----------------------------
<fg=cyan>Facade</>: {$module->facadeClass()}
<fg=cyan>Factory</>: {$factory}
<fg=cyan>Config</>: {$config}
<fg=cyan>Provider</>: {$provider}

TXT;
        }

        $this->output()->write($result);
    }

    /**
     * @param list<AppModule> $modules
     */
    private function generateSimpleView(array $modules): void
    {
        $rows = [];

        foreach ($modules as $module) {
            $rows[] = [
                $module->fullModuleName(),
                self::CHECK_SYMBOL, // facade is always true
                $module->factoryClass() !== null ? self::CHECK_SYMBOL : self::CROSS_SYMBOL,
                $module->configClass() !== null ? self::CHECK_SYMBOL : self::CROSS_SYMBOL,
                $module->providerClass() !== null ? self::CHECK_SYMBOL : self::CROSS_SYMBOL,
            ];
        }

        $table = new Table($this->output());
        $table->setStyle('box');
        $table->setHeaders(['Module namespace', 'Facade', 'Factory', 'Config', 'Dep. Provider']);
        $table->setRows($rows);
        $table->render();
    }
}
