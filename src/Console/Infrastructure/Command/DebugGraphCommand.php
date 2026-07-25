<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\ServiceResolverAwareTrait;
use JsonException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function in_array;
use function is_file;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

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
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text, mermaid, graphviz, or json', 'text')
            ->addOption('compare-to', 'c', InputOption::VALUE_REQUIRED, 'Path to a JSON graph (from --format=json) to diff the current graph against');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = ConsoleInput::option($input, 'format');
        if (!in_array($format, self::FORMATS, true)) {
            $output->writeln(sprintf('<error>Unknown format "%s". Use one of: text, mermaid, graphviz, json</error>', $format));

            return self::FAILURE;
        }

        $filter = ConsoleInput::argument($input, 'filter');
        $graph = $this->getFacade()->buildModuleGraph($filter);

        $compareTo = ConsoleInput::option($input, 'compare-to');
        if ($compareTo !== '') {
            return $this->writeDiff($compareTo, $graph, $output);
        }

        if ($graph === []) {
            $output->writeln(sprintf('<comment>No modules match filter "%s".</comment>', $filter));

            return self::SUCCESS;
        }

        $output->write($this->getFacade()->formatModuleGraph($graph, $format));

        return self::SUCCESS;
    }

    /**
     * Writes the markdown diff, or nothing at all when the graph is unchanged.
     *
     * Emitting nothing is the contract CI relies on: an empty report means
     * "no comment to post". A missing or unparseable baseline is a setup error,
     * not a graph change, so it fails loudly instead of reporting no changes.
     *
     * @param array<string, list<string>> $graph
     */
    private function writeDiff(string $baselinePath, array $graph, OutputInterface $output): int
    {
        $contents = is_file($baselinePath) ? file_get_contents($baselinePath) : false;
        if ($contents === false) {
            $output->writeln(sprintf('<error>Cannot read the graph to compare to: "%s"</error>', $baselinePath));

            return self::FAILURE;
        }

        try {
            /** @var array<string, list<string>> $base */
            $base = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $output->writeln(sprintf('<error>"%s" is not valid JSON: %s</error>', $baselinePath, $exception->getMessage()));

            return self::FAILURE;
        }

        $diff = $this->getFacade()->diffModuleGraph($base, $graph);
        if (!$diff->hasChanges()) {
            return self::SUCCESS;
        }

        $output->write($this->getFacade()->formatModuleGraphDiff($diff, $graph));

        return self::SUCCESS;
    }
}
