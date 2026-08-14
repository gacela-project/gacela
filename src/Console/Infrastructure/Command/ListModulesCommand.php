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

use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

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
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Display all the modules in detail')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output machine-readable JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $filter = ConsoleInput::argument($input, 'filter');
        $modules = $this->getFacade()->findAllAppModules($filter);

        if (ConsoleInput::format($input) === 'json') {
            $output->writeln(json_encode(
                $this->describe($modules),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return self::SUCCESS;
        }

        if ($modules === []) {
            ConsoleSection::noModulesFound($output, $filter);

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
     * Every module with every pillar, whatever `--detailed` says: that flag
     * chooses between two ways of printing for a reader, and a consumer that
     * asked for a document wants the fields either way.
     *
     * A filter matching nothing is an empty list rather than the sentence the
     * text report gives, for the same reason `profile:report --format=json`
     * stopped printing prose on the runs that report nothing: a consumer piping
     * this to a parser got a syntax error exactly when the answer was "none".
     *
     * @param list<AppModule> $modules
     *
     * @return list<array{module: string, fullModuleName: string, facade: string, factory: ?string, config: ?string, provider: ?string}>
     */
    private function describe(array $modules): array
    {
        $described = [];

        foreach ($modules as $module) {
            $described[] = [
                'module' => $module->moduleName(),
                'fullModuleName' => $module->fullModuleName(),
                'facade' => $module->facadeClass(),
                'factory' => $module->factoryClass(),
                'config' => $module->configClass(),
                'provider' => $module->providerClass(),
            ];
        }

        return $described;
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
