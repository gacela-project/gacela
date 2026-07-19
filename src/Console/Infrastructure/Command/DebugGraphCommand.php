<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function in_array;
use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
final class DebugGraphCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const FORMATS = ['text', 'mermaid', 'graphviz', 'json'];

    protected function configure(): void
    {
        $this->setName('debug:graph')
            ->setDescription('Show the module dependency graph (which module imports which)')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Only include modules matching this filter')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text, mermaid, graphviz, or json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = (string)$input->getOption('format');
        if (!in_array($format, self::FORMATS, true)) {
            $output->writeln(sprintf('<error>Unknown format "%s". Use one of: text, mermaid, graphviz, json</error>', $format));

            return self::FAILURE;
        }

        $filter = (string)$input->getArgument('filter');
        $graph = $this->getFacade()->buildModuleGraph($filter);

        if ($graph === []) {
            $output->writeln(sprintf('<comment>No modules match filter "%s".</comment>', $filter));

            return self::SUCCESS;
        }

        $output->write($this->getFacade()->formatModuleGraph($graph, $format));

        return self::SUCCESS;
    }
}
