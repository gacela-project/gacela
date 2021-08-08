<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Infrastructure\Command;

use Gacela\CodeGenerator\Domain\CommandArguments\CommandArgumentsParserInterface;
use Gacela\CodeGenerator\Domain\FileContent\FileContentGeneratorInterface;
use Gacela\CodeGenerator\Domain\FilenameSanitizer\FilenameSanitizerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeModuleCommand extends Command
{
    private CommandArgumentsParserInterface $argumentsParser;
    private FileContentGeneratorInterface $fileContentGenerator;
    private FilenameSanitizerInterface $filenameSanitizer;

    public function __construct(
        CommandArgumentsParserInterface $argumentsParser,
        FileContentGeneratorInterface $fileContentGenerator,
        FilenameSanitizerInterface $filenameSanitizer
    ) {
        $this->argumentsParser = $argumentsParser;
        $this->fileContentGenerator = $fileContentGenerator;
        $this->filenameSanitizer = $filenameSanitizer;
        parent::__construct('make:module');
    }

    protected function configure(): void
    {
        $this->setDescription('Generate a basic module with an empty ' . $this->getExpectedFilenames())
            ->addArgument('path', InputArgument::REQUIRED, 'The file path. For example "App/TestModule/TestSubModule"')
            ->addOption('short-name', 's', InputOption::VALUE_NONE, 'Remove module prefix to the class name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $path */
        $path = $input->getArgument('path');
        $commandArguments = $this->argumentsParser->parse($path);
        $shortName = (bool)$input->getOption('short-name');

        foreach ($this->filenameSanitizer->getExpectedFilenames() as $filename) {
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

    private function getExpectedFilenames(): string
    {
        return implode(', ', $this->filenameSanitizer->getExpectedFilenames());
    }
}
