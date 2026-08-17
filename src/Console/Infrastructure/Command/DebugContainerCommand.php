<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\Debug\DependencyTreeInspector;
use Gacela\Console\Application\Debug\DependencyTreeNode;
use Gacela\Console\Application\Debug\DependencyTreeRenderer;
use Gacela\Console\ConsoleFacade;
use Gacela\Container\ContainerStats;
use Gacela\Framework\Bootstrap\Package\DiscoveredPackage;
use Gacela\Framework\Bootstrap\Package\RefusedPackage;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Output\OutputInterface;

use function array_map;
use function class_exists;
use function count;
use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class DebugContainerCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('debug:container')
            ->setDescription('Display container debugging information (user bindings and plugins only)')
            ->setHelp($this->getHelpText())
            ->addArgument('class', InputArgument::OPTIONAL, 'Fully qualified class name to show dependency tree for')
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Show container statistics')
            ->addOption('tree', 't', InputOption::VALUE_NONE, 'Show dependency tree for specified class')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Report as a JSON document instead of text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $className */
        $className = $input->getArgument('class');
        $showStats = $input->getOption('stats') === true;
        $showTree = $input->getOption('tree') === true;
        $asJson = ConsoleInput::format($input) === 'json';

        // --stats takes precedence, even when combined with a class argument
        if ($showStats) {
            return $this->reportStats($output, $asJson);
        }

        if ($showTree && $className === null) {
            // A document either way, so a consumer piping this into a parser
            // gets one on the run that refused too.
            $output->writeln($asJson
                ? $this->encode(['error' => 'the --tree option requires a class name argument'])
                : '<error>The --tree option requires a class name argument</error>');

            return Command::FAILURE;
        }

        if ($className !== null) {
            return $this->reportDependencyTree($output, $className, $asJson);
        }

        return $this->reportStats($output, $asJson);
    }

    private function reportStats(OutputInterface $output, bool $asJson): int
    {
        if (!$asJson) {
            return $this->displayStats($output);
        }

        $stats = $this->getFacade()->getContainerStats();

        $output->writeln($this->encode([
            'stats' => [
                'registeredServices' => $stats->registeredServices,
                'frozenServices' => $stats->frozenServices,
                'factoryServices' => $stats->factoryServices,
                'bindings' => $stats->bindings,
                'cachedDependencies' => $stats->cachedDependencies,
                // Bytes rather than the "10 MB" the text prints: a document is
                // compared and charted, and a formatted string is neither.
                'processMemoryBytes' => $stats->processMemoryBytes,
            ],
            // An object keyed by abstract, which is how the text reads it out
            // and how a consumer looks one up. Empty stays `{}` rather than
            // becoming `[]`, so the shape does not change with the contents.
            'bindings' => (object)$this->getFacade()->getContainerBindings(),
            // Additive: a consumer reading `stats` or `bindings` is unaffected,
            // and one asking what an install put in the container has an answer.
            'packages' => $this->packagesDocument(),
        ]));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function packagesDocument(): array
    {
        $report = $this->getFacade()->getPackageDiscoveryReport();

        return [
            'installedJson' => $report->hasInstalledJson,
            'discoveryDisabled' => $report->discoveryDisabled,
            'declared' => $report->declaredNames(),
            'discovered' => array_map(
                static fn (DiscoveredPackage $package): array => [
                    'name' => $package->name,
                    'configFile' => $package->configFile,
                    'position' => $package->position,
                    'contributed' => (object)$package->contribution->items(),
                ],
                $report->discovered,
            ),
            'refused' => array_map(
                static fn (RefusedPackage $package): array => [
                    'name' => $package->name,
                    'configFile' => $package->configFile,
                    'reason' => $package->reason->value,
                ],
                $report->refused,
            ),
        ];
    }

    private function reportDependencyTree(OutputInterface $output, string $className, bool $asJson): int
    {
        if (!$asJson) {
            return $this->displayDependencyTree($output, $className);
        }

        if (!class_exists($className)) {
            $output->writeln($this->encode(['error' => sprintf('class "%s" does not exist', $className)]));

            return Command::FAILURE;
        }

        $inspection = (new DependencyTreeInspector())->inspect($className);

        $output->writeln($this->encode([
            'class' => $inspection->className,
            // A container that was never bootstrapped is a different answer
            // from a class with no dependencies, and the text report says so.
            'containerAvailable' => $inspection->containerAvailable,
            'fullyProvided' => $inspection->isFullyProvided(),
            'total' => count($inspection->nodes),
            'tree' => array_map($this->treeNode(...), $inspection->tree),
        ]));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function treeNode(DependencyTreeNode $node): array
    {
        return [
            'class' => $node->className,
            'parameter' => $node->parameter,
            'status' => $node->status->value,
            // The graph was cut here rather than recursed into, so an empty
            // `children` on a repeated node means "already drawn above".
            'repeated' => $node->repeated,
            'children' => array_map($this->treeNode(...), $node->children),
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function encode(array $document): string
    {
        return json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function displayStats(OutputInterface $output): int
    {
        ConsoleSection::title($output, 'Container Statistics');

        $stats = $this->getFacade()->getContainerStats();

        $output->writeln(sprintf('<fg=cyan>Registered Services:</> %d', $stats->registeredServices));
        $output->writeln(sprintf('<fg=cyan>Frozen Services:</> %d', $stats->frozenServices));
        $output->writeln(sprintf('<fg=cyan>Factory Services:</> %d', $stats->factoryServices));
        $output->writeln(sprintf('<fg=cyan>User Bindings:</> %d', $stats->bindings));
        $output->writeln(sprintf('<fg=cyan>Cached Dependencies:</> %d', $stats->cachedDependencies));
        // Whole-process memory, not this container's footprint -- the 2.0 rename
        // says so, where `memoryUsage` invited the wrong reading.
        $output->writeln(sprintf('<fg=cyan>Process Memory:</> %s', $stats->processMemoryFormatted()));
        $output->writeln('');

        $bindings = $this->getFacade()->getContainerBindings();

        // What a binding resolves to is the thing being debugged, and a count
        // does not say it. `debug:module` prints the same map, from the same
        // facade method, for one module's worth of it.
        if ($bindings !== []) {
            $output->writeln('<fg=cyan>Bindings:</>');
            foreach ($bindings as $abstract => $concrete) {
                $output->writeln(sprintf('  %s => %s', $abstract, $concrete));
            }

            $output->writeln('');
        }

        $this->displayDiscoveredPackages($output);

        // Every counter, not just the services: a binding registers none, so
        // keying the hint on that one printed "Container is empty" directly
        // under "User Bindings: 1" -- and checking that a binding landed is the
        // likeliest reason to run this at all.
        if ($this->holdsNothing($stats, $bindings)) {
            $output->writeln('<comment>Container is empty - nothing registered yet</comment>');
            $output->writeln('');
        }

        $output->writeln('<comment>Note: This shows only user-defined bindings and plugins.</comment>');
        $output->writeln("<comment>Gacela's internal services are not included in these statistics.</comment>");
        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * Who put what in here that this application never wrote down.
     *
     * A binding is printed above with no hint of where it came from, and a
     * discovered package is the one source a reader cannot find by searching the
     * project: nothing in it names the package. So this names the package, the
     * file that ran, and what that file declared.
     *
     * Silent when no package declares a config, which is every application that
     * has not installed one -- a section saying "none" on every project is a
     * section people stop reading.
     */
    private function displayDiscoveredPackages(OutputInterface $output): void
    {
        $report = $this->getFacade()->getPackageDiscoveryReport();

        if ($report->declarations === []) {
            return;
        }

        $output->writeln('<fg=cyan>Discovered packages:</>');

        if ($report->discoveryDisabled) {
            $output->writeln(sprintf(
                "  <comment>none read: dontDiscover(['*']) — %d package(s) declare one</comment>",
                count($report->declarations),
            ));
        }

        foreach ($report->discovered as $package) {
            $output->writeln(sprintf('  %d. %s', $package->position, $package->name));
            $output->writeln(sprintf('     %s', $package->configFile));

            foreach ($package->contribution->items() as $kind => $labels) {
                $output->writeln(sprintf('     %s: %s', $kind, implode(', ', $labels)));
            }

            if ($package->contribution->isEmpty()) {
                $output->writeln('     declares nothing');
            }
        }

        foreach ($report->refused as $package) {
            $output->writeln(sprintf('  ✗ %s — %s', $package->name, $package->reason->value));
        }

        $output->writeln('');
    }

    /**
     * Cached dependencies are left out on purpose: they are a resolution
     * artefact rather than something a project registered, so a container that
     * only ever resolved something is still one holding nothing of its own.
     *
     * @param array<string, string> $bindings
     */
    private function holdsNothing(ContainerStats $stats, array $bindings): bool
    {
        return $stats->registeredServices === 0
            && $stats->frozenServices === 0
            && $stats->factoryServices === 0
            && $bindings === [];
    }

    private function displayDependencyTree(OutputInterface $output, string $className): int
    {
        if (!class_exists($className)) {
            $output->writeln(sprintf('<error>Class "%s" does not exist</error>', $className));
            return Command::FAILURE;
        }

        ConsoleSection::title($output, sprintf('Dependency Tree for %s', $className));

        $inspection = (new DependencyTreeInspector())->inspect($className);

        if ($inspection->nodes === []) {
            $output->writeln(sprintf('Class "%s" has no dependencies', $className));
            $output->writeln('');
            return Command::SUCCESS;
        }

        foreach ((new DependencyTreeRenderer())->render($inspection->tree) as $line) {
            $output->writeln($line);
        }

        $output->writeln('');
        // Distinct classes, so one pulled in by three parents counts once and
        // is drawn three times.
        $output->writeln(sprintf('<fg=cyan>Total Dependencies:</> %d', count($inspection->nodes)));
        $output->writeln('');
        $output->writeln('<comment>This tree shows only user-defined dependencies.</comment>');
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
This command displays debugging information about the Gacela dependency injection container.

<comment>IMPORTANT:</comment> This command shows only user-defined bindings and plugins configured in your
application. It does NOT show Gacela's internal services, framework classes, or
auto-wired dependencies that are resolved automatically.

<info>Statistics Mode:</info>
  Shows an overview of the container state including number of registered services,
  frozen services, factory services, user bindings, cached dependencies, and memory usage.

<info>Dependency Tree Mode:</info>
  Shows the complete dependency chain for a given class, displaying all constructor
  dependencies recursively. This helps identify circular dependencies and understand
  how services are wired together.

<info>Examples:</info>
  # Show container statistics
  bin/gacela debug:container
  bin/gacela debug:container --stats

  # Show dependency tree for a class
  bin/gacela debug:container "App\MyModule\MyFacade"
  bin/gacela debug:container "App\MyModule\MyFacade" --tree
HELP;
    }
}
