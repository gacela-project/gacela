<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Infrastructure\Command;

use Gacela\CodeGenerator\CodeGeneratorFacade;
use Gacela\CodeGenerator\Domain\FilenameSanitizer\FilenameSanitizer;
use Gacela\Framework\DocBlockResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method CodeGeneratorFacade getFacade()
 */
final class MakeModuleCommand extends Command
{
    use DocBlockResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('make:module')
            ->setDescription('Generate a basic module with an empty ' . $this->getExpectedFilenames())
            ->addArgument('path', InputArgument::REQUIRED, 'The file path. For example "App/TestModule/TestSubModule"')
            ->addOption('short-name', 's', InputOption::VALUE_NONE, 'Remove module prefix to the class name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $path */
        $path = $input->getArgument('path');
        $commandArguments = $this->getFacade()->parseArguments($path);
        $shortName = (bool)$input->getOption('short-name');

        foreach (FilenameSanitizer::EXPECTED_FILENAMES as $filename) {
            $fullPath = $this->getFacade()->generateFileContent(
                $commandArguments,
                $filename,
                $shortName
            );
            $output->writeln("> Path '{$fullPath}' created successfully");
        }

        $pieces = explode('/', $commandArguments->directory());
        $moduleName = end($pieces);
        $output->writeln("Module '{$moduleName}' created successfully");

        return self::SUCCESS;
    }

    private function getExpectedFilenames(): string
    {
        return implode(', ', FilenameSanitizer::EXPECTED_FILENAMES);
    }
}
