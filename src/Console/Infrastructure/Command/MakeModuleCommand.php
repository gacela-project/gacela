<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\CommandArguments\CommandArguments;
use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
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
final class MakeModuleCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const TEMPLATES = ['basic', 'service', 'minimal'];

    protected function configure(): void
    {
        $this->setName('make:module')
            ->setDescription('Generate a basic module with an empty ' . $this->getExpectedFilenames())
            ->addArgument('path', InputArgument::REQUIRED, 'The file path. For example "App/TestModule/TestSubModule"')
            ->addOption('short-name', 's', InputOption::VALUE_NONE, 'Remove module prefix to the class name')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Module template: basic, service (Facade wired to a Domain service), or minimal (Facade + Factory only)', 'basic')
            ->addOption('minimal', null, InputOption::VALUE_NONE, 'Scaffold only the Facade and Factory pillars (shorthand for --template=minimal)')
            ->addOption('with-tests', null, InputOption::VALUE_NONE, 'Also scaffold a facade test (service template only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $template = (string)$input->getOption('template');
        if ((bool)$input->getOption('minimal')) {
            $template = 'minimal';
        }

        if (!in_array($template, self::TEMPLATES, true)) {
            $output->writeln(sprintf(
                '<error>Unknown template "%s". Use one of: %s</error>',
                $template,
                implode(', ', self::TEMPLATES),
            ));

            return self::FAILURE;
        }

        /** @var string $path */
        $path = $input->getArgument('path');
        $commandArguments = $this->getFacade()->parseArguments($path);
        $shortName = (bool)$input->getOption('short-name');

        if ($template === 'service') {
            $this->generateServiceModule($commandArguments, $shortName, (bool)$input->getOption('with-tests'), $output);
        } elseif ($template === 'minimal') {
            $this->generateMinimalModule($commandArguments, $shortName, $output);
        } else {
            foreach (FilenameSanitizer::EXPECTED_FILENAMES as $filename) {
                $fullPath = $this->getFacade()->generateFileContent(
                    $commandArguments,
                    $filename,
                    $shortName,
                );
                $output->writeln(sprintf("> Path '%s' created successfully", $fullPath));
            }
        }

        $pieces = explode('/', $commandArguments->directory());
        $moduleName = end($pieces);
        $output->writeln(sprintf("Module '%s' created successfully", $moduleName));

        return self::SUCCESS;
    }

    private function generateServiceModule(
        CommandArguments $commandArguments,
        bool $shortName,
        bool $withTests,
        OutputInterface $output,
    ): void {
        $files = [];
        foreach (FilenameSanitizer::EXPECTED_FILENAMES as $filename) {
            $files[] = [$filename, ''];
        }

        $files[] = ['Service', 'Domain'];
        if ($withTests) {
            $files[] = ['FacadeTest', 'Tests'];
        }

        foreach ($files as [$filename, $subDirectory]) {
            $fullPath = $this->getFacade()->generateServiceFileContent(
                $commandArguments,
                $filename,
                $shortName,
                $subDirectory,
            );
            $output->writeln(sprintf("> Path '%s' created successfully", $fullPath));
        }
    }

    /**
     * The `minimal` template scaffolds only the Facade and Factory pillars.
     * Config and Provider are optional: add them when the module actually
     * reads config or wires external dependencies.
     */
    private function generateMinimalModule(
        CommandArguments $commandArguments,
        bool $shortName,
        OutputInterface $output,
    ): void {
        foreach ([FilenameSanitizer::FACADE, FilenameSanitizer::FACTORY] as $filename) {
            $fullPath = $this->getFacade()->generateFileContent(
                $commandArguments,
                $filename,
                $shortName,
            );
            $output->writeln(sprintf("> Path '%s' created successfully", $fullPath));
        }
    }

    private function getExpectedFilenames(): string
    {
        return implode(', ', FilenameSanitizer::EXPECTED_FILENAMES);
    }
}
