<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
final class MakeModuleCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('make:module')
            ->setDescription('Generate a module with optional templates and scaffolding')
            ->addArgument('path', InputArgument::REQUIRED, 'The file path. For example "App/TestModule/TestSubModule"')
            ->addOption('short-name', 's', InputOption::VALUE_NONE, 'Remove module prefix to the class name')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Template type: crud, api, or cli', 'basic')
            ->addOption('with-tests', null, InputOption::VALUE_NONE, 'Generate test files')
            ->addOption('with-api', null, InputOption::VALUE_NONE, 'Generate API controller stubs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $path */
        $path = $input->getArgument('path');
        $commandArguments = $this->getFacade()->parseArguments($path);
        $shortName = (bool)$input->getOption('short-name');
        $template = (string)$input->getOption('template');
        $withTests = (bool)$input->getOption('with-tests');
        $withApi = (bool)$input->getOption('with-api');

        $output->writeln(sprintf('<info>Generating module with template: %s</info>', $template));

        // Generate core module files
        foreach (FilenameSanitizer::EXPECTED_FILENAMES as $filename) {
            $fullPath = $this->getFacade()->generateFileContent(
                $commandArguments,
                $filename,
                $shortName,
            );
            $output->writeln(sprintf("> Path '%s' created successfully", $fullPath));
        }

        // Generate template-specific files
        $templateFiles = $this->getFacade()->generateTemplateFiles(
            $commandArguments,
            $template,
            $withTests,
            $withApi,
        );

        foreach ($templateFiles as $file) {
            $output->writeln(sprintf("> Path '%s' created successfully", $file));
        }

        $pieces = explode('/', $commandArguments->directory());
        $moduleName = end($pieces);
        $output->writeln(sprintf("<info>Module '%s' created successfully</info>", $moduleName));

        if ($withTests) {
            $output->writeln('<comment>Test files generated. Run: vendor/bin/phpunit</comment>');
        }

        if ($withApi) {
            $output->writeln('<comment>API controller stubs generated.</comment>');
        }

        return self::SUCCESS;
    }
}
