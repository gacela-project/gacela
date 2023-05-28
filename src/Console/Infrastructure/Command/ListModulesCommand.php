<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\DocBlockResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
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
            ->setDescription('Render all modules found');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $modules = $this->getFacade()->findAllAppModules();

        $table = new Table($output);
        $table->setHeaders(['Module', 'Facade']);

        foreach ($modules as $module) {
            $table->addRow([
                $module->moduleName(),
                $module->facadeClass(),
            ]);
        }

        $output->writeln('Modules found:');
        $table->render();

        return self::SUCCESS;
    }
}
