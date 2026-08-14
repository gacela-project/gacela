<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;

use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class ListModulesCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const CHECK_SYMBOL = 'x';

    private const CROSS_SYMBOL = ' ';

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

        $filter = ConsoleInput::argument($input, 'filter');
        $modules = $this->getFacade()->findAllAppModules($filter);

        if ($modules === []) {
            $this->reportNothingFound($output, $filter);

            return self::SUCCESS;
        }

        $this->generateListOfModules(
            $input->getOption('detailed') === true,
            $modules,
        );

        return self::SUCCESS;
    }

    private function output(): OutputInterface
    {
        return $this->output ?? new ConsoleOutput();
    }

    /**
     * A filter that matched nothing and a project where nothing is a module are
     * different answers, and `No modules match filter ""` gave the second one
     * in the words of the first -- quoting an empty filter as though the reader
     * had typed it.
     *
     * The hint is the cause worth naming: discovery reflects on the class to
     * see whether it descends from `AbstractFacade`, so a file whose namespace
     * composer cannot map is skipped in silence. The files are right there on
     * disk, which is what makes the empty list read as a bug in the command.
     */
    private function reportNothingFound(OutputInterface $output, string $filter): void
    {
        if ($filter !== '') {
            $output->writeln(sprintf('<comment>No modules match filter "%s".</comment>', $filter));

            return;
        }

        $output->writeln('<comment>No modules found.</comment>');
        $output->writeln(
            'A module is found by its Facade: the filename carries the suffix, and the class has to be'
            . ' autoloadable. If the files are there, check the psr-4 mapping in composer.json.',
        );
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
        $table->setHeaders(['Module namespace', 'Facade', 'Factory', 'Config', 'Provider']);
        $table->setRows($rows);
        $table->render();
    }
}
