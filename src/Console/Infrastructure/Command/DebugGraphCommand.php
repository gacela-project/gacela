<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\ModuleGraph\CycleAllowList;
use Gacela\Console\Domain\ModuleGraph\CycleCheckResult;
use Gacela\Console\Domain\ModuleGraph\MalformedCycleAllowListException;
use Gacela\Console\Domain\ModuleGraph\ModuleRuleCheckResult;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Gacela\StaticAnalysis\ModuleRules\MalformedModuleRulesException;
use Gacela\StaticAnalysis\ModuleRules\ModuleRuleSet;
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
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
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
            ->addOption('allowed-cycles', null, InputOption::VALUE_REQUIRED, 'Path to a JSON file of reviewed cycles, each with a "modules" list and a "reason"')
            ->addOption('rules', null, InputOption::VALUE_REQUIRED, 'Path to a JSON file of declared module rules, each with a "from", either "allow" or "deny", and a "reason"');
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
            return $this->check($graph, $filter, $format, $input, $output);
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
     * The gate: cycles nobody declared, allowances that outlived their cycle,
     * and dependencies a declared rule forbids.
     *
     * Both halves are self-invalidating, which is the point. A reviewed cycle
     * recorded only in prose is a decision the tooling cannot see, and an
     * allow-list that outlives what it allows is worse, because it looks like
     * the check is still watching. A module rule about a module nobody has any
     * more reads exactly the same way.
     *
     * @param array<string, list<string>> $graph
     */
    private function check(array $graph, string $filter, string $format, InputInterface $input, OutputInterface $output): int
    {
        $rulesPath = ConsoleInput::option($input, 'rules');
        if ($rulesPath !== '' && $filter !== '') {
            $output->writeln(
                '<error>--rules cannot be combined with a filter: in a narrowed graph, a rule about a filtered-out module is indistinguishable from a rule about a module that no longer exists.</error>',
            );

            return self::FAILURE;
        }

        $allowList = $this->readAllowList(ConsoleInput::option($input, 'allowed-cycles'), $output);
        if (!$allowList instanceof CycleAllowList) {
            return self::FAILURE;
        }

        $rules = $this->readRuleSet($rulesPath, $output);
        if (!$rules instanceof ModuleRuleSet) {
            return self::FAILURE;
        }

        $cycles = $this->getFacade()->detectModuleCycles($graph);
        $cycleResult = $allowList->check($cycles);
        $ruleResult = $this->getFacade()->checkModuleRules($graph, $rules);

        if ($format === 'json') {
            $output->writeln($this->checkReportAsJson($cycleResult, $ruleResult));
        } else {
            $this->writeCycleReport($cycles, $allowList, $cycleResult, $output);
            $this->writeRuleReport($ruleResult, $rules, $output);
        }

        return $cycleResult->isClean() && $ruleResult->isClean() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Null after reporting why the allow list is unusable; an empty list when
     * none was asked for.
     */
    private function readAllowList(string $path, OutputInterface $output): ?CycleAllowList
    {
        if ($path === '') {
            return CycleAllowList::empty();
        }

        /** @var list<mixed>|null $decoded */
        $decoded = $this->readJsonFile($path, 'allowed cycles', $output);
        if ($decoded === null) {
            return null;
        }

        try {
            return CycleAllowList::fromDecodedJson($decoded);
        } catch (MalformedCycleAllowListException $malformedCycleAllowListException) {
            $output->writeln(sprintf('<error>%s</error>', $malformedCycleAllowListException->getMessage()));

            return null;
        }
    }

    /**
     * Null after reporting why the rules file is unusable; an empty set when
     * none was asked for.
     */
    private function readRuleSet(string $path, OutputInterface $output): ?ModuleRuleSet
    {
        if ($path === '') {
            return ModuleRuleSet::empty();
        }

        try {
            return ModuleRuleSet::fromFile($path);
        } catch (MalformedModuleRulesException $malformedModuleRulesException) {
            $output->writeln(sprintf('<error>%s</error>', $malformedModuleRulesException->getMessage()));

            return null;
        }
    }

    /**
     * @param list<list<string>> $cycles
     */
    private function writeCycleReport(array $cycles, CycleAllowList $allowList, CycleCheckResult $result, OutputInterface $output): void
    {
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

        if ($result->isClean()) {
            $output->writeln('<fg=green>✓ No undeclared module dependency cycles</>');
        }
    }

    /**
     * Silent when no rules were declared: a green line about a check that never
     * ran is the thing this whole file is written against.
     */
    private function writeRuleReport(ModuleRuleCheckResult $result, ModuleRuleSet $rules, OutputInterface $output): void
    {
        foreach ($result->violations as $violation) {
            $output->writeln(sprintf(
                '<error>✗ Forbidden dependency:</error> %s -> %s <fg=gray>(%s)</>',
                $violation->from,
                $violation->to,
                $violation->reason,
            ));
        }

        foreach ($result->unknownNamespaces as $namespace) {
            $output->writeln(sprintf(
                '<error>✗ Module rule governs nothing:</error> %s matches no module. Remove the rule, or fix the namespace.',
                $namespace,
            ));
        }

        if ($rules->isEmpty()) {
            return;
        }

        if ($result->isClean()) {
            $output->writeln('<fg=green>✓ No forbidden module dependencies</>');
        }
    }

    /**
     * The same findings as a machine-readable report, for a CI job that wants to
     * do more than test an exit code.
     */
    private function checkReportAsJson(CycleCheckResult $cycleResult, ModuleRuleCheckResult $ruleResult): string
    {
        $forbidden = [];
        foreach ($ruleResult->violations as $violation) {
            $forbidden[] = [
                'from' => $violation->from,
                'to' => $violation->to,
                'reason' => $violation->reason,
            ];
        }

        return json_encode([
            'undeclaredCycles' => $cycleResult->undeclaredCycles,
            'staleAllowedCycles' => $cycleResult->staleAllowances,
            'forbiddenDependencies' => $forbidden,
            'unknownRuleNamespaces' => $ruleResult->unknownNamespaces,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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
        } catch (JsonException $jsonException) {
            $output->writeln(sprintf('<error>"%s" is not valid JSON: %s</error>', $path, $jsonException->getMessage()));

            return null;
        }

        if (!is_array($decoded)) {
            $output->writeln(sprintf('<error>"%s" must contain a JSON array or object.</error>', $path));

            return null;
        }

        return $decoded;
    }
}
