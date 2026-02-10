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
use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;
use function str_replace;

/**
 * @method ConsoleFacade getFacade()
 */
final class DocsGenerateCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('docs:generate')
            ->setDescription('Generate module documentation')
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output directory',
                'docs/modules',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputDir = (string)$input->getOption('output');

        $output->writeln('<info>Generating module documentation...</info>');

        $modules = $this->getFacade()->findAllAppModules();

        if (count($modules) === 0) {
            $output->writeln('<comment>No modules found.</comment>');

            return self::SUCCESS;
        }

        // Analyze dependencies
        $dependencies = $this->getFacade()->analyzeModuleDependencies($modules);
        $dependencyMap = [];

        foreach ($dependencies as $dep) {
            $dependencyMap[$dep->moduleName()] = array_map(
                static fn (string $d): array => ['from' => $dep->moduleName(), 'to' => $d],
                $dep->dependencies(),
            );
        }

        // Generate documentation for each module
        $this->ensureDirectoryExists($outputDir);

        foreach ($modules as $module) {
            $moduleDeps = $dependencyMap[$module->fullModuleName()] ?? [];
            $docContent = $this->getFacade()->generateModuleDocumentation($module, $moduleDeps);

            $fileName = str_replace('\\', '_', $module->fullModuleName()) . '.md';
            $filePath = $outputDir . '/' . $fileName;

            file_put_contents($filePath, $docContent);

            $output->writeln(sprintf('  <info>✓</info> Generated: %s', $filePath));
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Documentation generated for %d module(s) in: %s</info>',
            count($modules),
            $outputDir,
        ));

        return self::SUCCESS;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
