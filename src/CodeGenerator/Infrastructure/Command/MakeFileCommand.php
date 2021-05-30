<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Infrastructure\Command;

use Gacela\CodeGenerator\Domain\CommandArgumentsParser;
use Gacela\CodeGenerator\Domain\FileContentGenerator;
use Gacela\CodeGenerator\Domain\FilenameSanitizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeFileCommand extends Command
{
    private CommandArgumentsParser $argumentsParser;
    private FilenameSanitizer $filenameSanitizer;
    private FileContentGenerator $fileContentGenerator;

    public function __construct(
        CommandArgumentsParser $argumentsParser,
        FilenameSanitizer $filenameSanitizer,
        FileContentGenerator $fileContentGenerator
    ) {
        parent::__construct('make:file');
        $this->argumentsParser = $argumentsParser;
        $this->filenameSanitizer = $filenameSanitizer;
        $this->fileContentGenerator = $fileContentGenerator;
    }

    protected function configure(): void
    {
        $this->setDescription('Generate a ' . FilenameSanitizer::expectedFilenames())
            ->addArgument('path', InputArgument::REQUIRED, 'The file path. For example "App/TestModule/TestSubModule"')
            ->addArgument('filenames', InputArgument::REQUIRED|InputArgument::IS_ARRAY, FilenameSanitizer::expectedFilenames(' | '));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filenames = array_map(
            fn (string $raw): string => $this->filenameSanitizer->sanitize($raw),
            (array)$input->getArgument('filenames')
        );

        /** @var string $path */
        $path = $input->getArgument('path');
        $commandArguments = $this->argumentsParser->parse($path);

        foreach ($filenames as $filename) {
            $this->fileContentGenerator->generate($commandArguments, $filename);
            $output->writeln("> Path '$path/$filename' created successfully");
        }

        return 0;
    }
}
