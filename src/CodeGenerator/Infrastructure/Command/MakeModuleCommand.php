<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Infrastructure\Command;

use Gacela\CodeGenerator\Domain\CommandArgumentsParser;
use Gacela\CodeGenerator\Domain\FileContentGenerator;
use Gacela\CodeGenerator\Domain\FilenameSanitizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeModuleCommand extends Command
{
    private CommandArgumentsParser $argumentsParser;
    private FileContentGenerator $fileContentGenerator;

    public function __construct(
        CommandArgumentsParser $argumentsParser,
        FileContentGenerator $fileContentGenerator
    ) {
        parent::__construct('make:module');
        $this->argumentsParser = $argumentsParser;
        $this->fileContentGenerator = $fileContentGenerator;
    }

    protected function configure(): void
    {
        $this->setDescription('Generate a basic module with an empty ' . FilenameSanitizer::expectedFilenames())
            ->addArgument('path', InputArgument::REQUIRED, 'The file path. For example "App/TestModule/TestSubModule"')
            ->addOption('short-name', 's', InputOption::VALUE_NONE, 'Remove module prefix to the class name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $path */
        $path = $input->getArgument('path');
        $commandArguments = $this->argumentsParser->parse($path);
        $shortName = (bool)$input->getOption('short-name');

        foreach (FilenameSanitizer::EXPECTED_FILENAMES as $filename) {
            $fullPath = $this->fileContentGenerator->generate(
                $commandArguments,
                $filename,
                $shortName
            );
            $output->writeln("> Path '$fullPath' created successfully");
        }

        $pieces = explode('/', $commandArguments->directory());
        $moduleName = end($pieces);
        $output->writeln("Module '$moduleName' created successfully");

        return self::SUCCESS;
    }
}
