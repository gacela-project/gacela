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

use function class_exists;
use function count;
use function sprintf;
use function strlen;

/**
 * @method ConsoleFacade getFacade()
 */
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
            ->addOption('tree', 't', InputOption::VALUE_NONE, 'Show dependency tree for specified class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $className */
        $className = $input->getArgument('class');
        /** @var bool $showTree */
        $showTree = (bool) $input->getOption('tree');

        // Validate arguments
        if ($showTree && $className === null) {
            $output->writeln('<error>The --tree option requires a class name argument</error>');
            return Command::FAILURE;
        }

        // If class provided without --tree flag, assume --tree
        if ($className !== null) {
            return $this->displayDependencyTree($output, $className);
        }

        // Default to showing stats if no arguments
        return $this->displayStats($output);
    }

    private function displayStats(OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>Container Statistics</info>');
        $output->writeln('<info>' . str_repeat('=', 60) . '</info>');
        $output->writeln('');

        $stats = $this->getFacade()->getContainerStats();

        $output->writeln(sprintf('<fg=cyan>Registered Services:</> %d', $stats['registered_services']));
        $output->writeln(sprintf('<fg=cyan>Frozen Services:</> %d', $stats['frozen_services']));
        $output->writeln(sprintf('<fg=cyan>Factory Services:</> %d', $stats['factory_services']));
        $output->writeln(sprintf('<fg=cyan>User Bindings:</> %d', $stats['bindings']));
        $output->writeln(sprintf('<fg=cyan>Cached Dependencies:</> %d', $stats['cached_dependencies']));
        $output->writeln(sprintf('<fg=cyan>Memory Usage:</> %s', $stats['memory_usage']));
        $output->writeln('');

        if ($stats['registered_services'] === 0) {
            $output->writeln('<comment>Container is empty - no services registered yet</comment>');
            $output->writeln('');
        }

        $output->writeln('<comment>Note: This shows only user-defined bindings and plugins.</comment>');
        $output->writeln("<comment>Gacela's internal services are not included in these statistics.</comment>");
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function displayDependencyTree(OutputInterface $output, string $className): int
    {
        if (!class_exists($className)) {
            $output->writeln(sprintf('<error>Class "%s" does not exist</error>', $className));
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>Dependency Tree for %s</info>', $className));
        $output->writeln('<info>' . str_repeat('=', 60) . '</info>');
        $output->writeln('');

        $dependencyTree = $this->getFacade()->getContainerDependencyTree($className);

        if ($dependencyTree === []) {
            $output->writeln(sprintf('Class "%s" has no dependencies', $className));
            $output->writeln('');
            return Command::SUCCESS;
        }

        $counter = 1;
        foreach ($dependencyTree as $dependency) {
            $indent = $this->getIndentLevel($dependency);
            $cleanDependency = $this->cleanDependency($dependency);

            $output->writeln(sprintf('%s%d. %s', str_repeat('  ', $indent), $counter, $cleanDependency));
            ++$counter;
        }

        $output->writeln('');
        $output->writeln(sprintf('<fg=cyan>Total Dependencies:</> %d', count($dependencyTree)));
        $output->writeln('');
        $output->writeln('<comment>Note: Indentation shows dependency depth.</comment>');
        $output->writeln('<comment>This tree shows only user-defined dependencies.</comment>');
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function getIndentLevel(string $dependency): int
    {
        // Count leading spaces to determine depth
        $matches = [];
        if (preg_match('/^(\s*)/', $dependency, $matches) === 1) {
            return (int)(strlen($matches[1]) / 2);
        }

        return 0;
    }

    private function cleanDependency(string $dependency): string
    {
        return trim($dependency);
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
