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

final class MakeFileCommand extends Command
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
        parent::__construct('make:file');
    }

    protected function configure(): void
    {
        $this->setDescription('Generate a ' . $this->getExpectedFilenames())
            ->addArgument('path', InputArgument::REQUIRED, 'The file path. For example "App/TestModule/TestSubModule"')
            ->addArgument('filenames', InputArgument::REQUIRED | InputArgument::IS_ARRAY, $this->getExpectedFilenames())
            ->addOption('short-name', 's', InputOption::VALUE_NONE, 'Remove module prefix to the class name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var array $inputFileNames */
        $inputFileNames = $input->getArgument('filenames');
        $filenames = array_map(
            fn (string $raw): string => $this->filenameSanitizer->sanitize($raw),
            $inputFileNames
        );

        /** @var string $path */
        $path = $input->getArgument('path');
        $commandArguments = $this->argumentsParser->parse($path);
        $shortName = (bool)$input->getOption('short-name');

        foreach ($filenames as $filename) {
            $absolutePath = $this->fileContentGenerator->generate(
                $commandArguments,
                $filename,
                $shortName
            );
            $output->writeln("> Path '$absolutePath' created successfully");
        }

        return self::SUCCESS;
    }

    private function getExpectedFilenames(string $glue = ', '): string
    {
        return implode($glue, $this->filenameSanitizer->getExpectedFilenames());
    }
}
