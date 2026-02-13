<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
final class AnalyzeDependenciesCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('analyze:dependencies')
            ->setDescription('Analyze and visualize module dependencies')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format: mermaid, graphviz (dot), or json',
                'json',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path (optional)',
            )
            ->addOption(
                'check-circular',
                'c',
                InputOption::VALUE_NONE,
                'Check for circular dependencies',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = (string)$input->getOption('format');
        $outputFile = $input->getOption('output');
        $checkCircular = (bool)$input->getOption('check-circular');

        $output->writeln('<info>Analyzing module dependencies...</info>');

        $modules = $this->getFacade()->findAllAppModules();

        if (count($modules) === 0) {
            $output->writeln('<comment>No modules found.</comment>');

            return self::SUCCESS;
        }

        $dependencies = $this->getFacade()->analyzeModuleDependencies($modules);

        if ($checkCircular) {
            $circular = $this->getFacade()->detectCircularDependencies($dependencies);

            if (count($circular) > 0) {
                $output->writeln('<error>Circular dependencies detected:</error>');
                foreach ($circular as $cycle) {
                    $output->writeln(sprintf(
                        '  <fg=red>%s</> -> <fg=red>%s</>',
                        $cycle['from'],
                        $cycle['to'],
                    ));
                }

                return self::FAILURE;
            }

            $output->writeln('<info>No circular dependencies detected.</info>');
        }

        $formattedOutput = $this->getFacade()->formatDependencies($dependencies, $format);

        if ($outputFile !== null) {
            file_put_contents((string)$outputFile, $formattedOutput);
            $output->writeln(sprintf('<info>Dependency graph saved to: %s</info>', $outputFile));
        } else {
            $output->writeln($formattedOutput);
        }

        $this->printStatistics($output, $dependencies);

        return self::SUCCESS;
    }

    /**
     * @param list<\Gacela\Console\Domain\DependencyAnalyzer\TModuleDependency> $dependencies
     */
    private function printStatistics(OutputInterface $output, array $dependencies): void
    {
        $totalModules = count($dependencies);
        $modulesWithDeps = 0;
        $maxDepth = 0;
        $totalDeps = 0;

        foreach ($dependencies as $dep) {
            if ($dep->hasDependencies()) {
                ++$modulesWithDeps;
                $totalDeps += count($dep->dependencies());
            }

            $maxDepth = max($maxDepth, $dep->depth());
        }

        $output->writeln('');
        $output->writeln('<info>Statistics:</info>');
        $output->writeln(sprintf('  Total modules: <comment>%d</comment>', $totalModules));
        $output->writeln(sprintf('  Modules with dependencies: <comment>%d</comment>', $modulesWithDeps));
        $output->writeln(sprintf('  Total dependencies: <comment>%d</comment>', $totalDeps));
        $output->writeln(sprintf('  Maximum dependency depth: <comment>%d</comment>', $maxDepth));
    }
}
