<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\ModuleGraph\CycleAllowList;
use Gacela\Console\Domain\ModuleGraph\MalformedCycleAllowListException;
use Gacela\Framework\ServiceResolverAwareTrait;
use JsonException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function implode;
use function in_array;
use function is_array;
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
            ->addOption('compare-to', 'c', InputOption::VALUE_REQUIRED, 'Path to a JSON graph (from --format=json) to diff the current graph against')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Exit non-zero when the graph contains a dependency cycle')
            ->addOption('allowed-cycles', null, InputOption::VALUE_REQUIRED, 'Path to a JSON file of reviewed cycles, each with a "modules" list and a "reason"');
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

        if ($input->getOption('check') === true) {
            return $this->checkCycles($graph, ConsoleInput::option($input, 'allowed-cycles'), $output);
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
        /** @var array<string, list<string>>|null $base */
        $base = $this->readJsonFile($baselinePath, 'graph to compare to', $output);
        if ($base === null) {
            return self::FAILURE;
        }

        $diff = $this->getFacade()->diffModuleGraph($base, $graph);
        if (!$diff->hasChanges()) {
            return self::SUCCESS;
        }

        $output->write($this->getFacade()->formatModuleGraphDiff($diff, $graph));

        return self::SUCCESS;
    }

    /**
     * Fails on an undeclared cycle, and equally on an allowance that no longer
     * matches one.
     *
     * The second half is the point. A reviewed cycle recorded only in prose is a
     * decision the tooling cannot see, which makes it indistinguishable from a
     * cycle nobody noticed; but an allow-list that outlives what it allows is
     * worse, because it looks like the check is still watching.
     *
     * @param array<string, list<string>> $graph
     */
    private function checkCycles(array $graph, string $allowListPath, OutputInterface $output): int
    {
        $allowList = CycleAllowList::empty();

        if ($allowListPath !== '') {
            /** @var list<mixed>|null $decoded */
            $decoded = $this->readJsonFile($allowListPath, 'allowed cycles', $output);
            if ($decoded === null) {
                return self::FAILURE;
            }

            try {
                $allowList = CycleAllowList::fromDecodedJson($decoded);
            } catch (MalformedCycleAllowListException $exception) {
                $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

                return self::FAILURE;
            }
        }

        $cycles = $this->getFacade()->detectModuleCycles($graph);
        $result = $allowList->check($cycles);

        foreach ($cycles as $cycle) {
            $reason = $allowList->reasonFor($cycle);
            if ($reason !== null) {
                $output->writeln(sprintf('<comment>~ allowed cycle:</comment> %s <fg=gray>(%s)</>', implode(' -> ', $cycle), $reason));
            }
        }

        foreach ($result->undeclaredCycles as $cycle) {
            $output->writeln(sprintf('<error>✗ Dependency cycle:</error> %s', implode(' -> ', $cycle)));
        }

        foreach ($result->staleAllowances as $cycle) {
            $output->writeln(sprintf(
                '<error>✗ Allowed cycle no longer exists:</error> %s. Remove it from the allow list.',
                implode(' -> ', $cycle),
            ));
        }

        if (!$result->isClean()) {
            return self::FAILURE;
        }

        $output->writeln('<fg=green>✓ No undeclared module dependency cycles</>');

        return self::SUCCESS;
    }

    /**
     * @return array<mixed>|null null after reporting why the file is unusable
     */
    private function readJsonFile(string $path, string $label, OutputInterface $output): ?array
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            $output->writeln(sprintf('<error>Cannot read the %s: "%s"</error>', $label, $path));

            return null;
        }

        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $output->writeln(sprintf('<error>"%s" is not valid JSON: %s</error>', $path, $exception->getMessage()));

            return null;
        }

        if (!is_array($decoded)) {
            $output->writeln(sprintf('<error>"%s" must contain a JSON array or object.</error>', $path));

            return null;
        }

        return $decoded;
    }
}
