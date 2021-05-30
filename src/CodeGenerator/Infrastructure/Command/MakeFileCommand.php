<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Infrastructure\Command;

use Gacela\CodeGenerator\Domain\FileContentGenerator;
use Gacela\CodeGenerator\Domain\CommandArgumentsParser;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function in_array;
use function json_encode;

final class MakeFileCommand extends Command
{
    private const EXPECTED_FILENAMES = ['Facade', 'Factory', 'Config', 'DependencyProvider'];

    private CommandArgumentsParser $argumentsParser;
    private FileContentGenerator $fileContentGenerator;

    public function __construct(
        CommandArgumentsParser $argumentsParser,
        FileContentGenerator $fileContentGenerator
    ) {
        parent::__construct('make:file');
        $this->argumentsParser = $argumentsParser;
        $this->fileContentGenerator = $fileContentGenerator;
    }

    protected function configure(): void
    {
        $this->setDescription('Generate a Facade|Factory|Config|DependencyProvider.')
            ->addArgument('filename', InputArgument::REQUIRED, 'Facade|Factory|Config|DependencyProvider')
            ->addArgument('path', InputArgument::REQUIRED, 'The file path. For example "App/TestModule/TestSubModule"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $path */
        $path = $input->getArgument('path');
        $commandArguments = $this->argumentsParser->parse($path);

        /** @var string $filename */
        $filename = $input->getArgument('filename');
        $filename = ucfirst($filename);
        $this->verifyFilename($filename);
        $this->fileContentGenerator->generate($commandArguments, $filename);

        $output->writeln("> Path '$path/$filename' created successfully");

        return 0;
    }

    private function verifyFilename(string $filename): void
    {
        if (!in_array($filename, self::EXPECTED_FILENAMES)) {
            throw new RuntimeException(sprintf(
                'Filename must be one of these values: %s',
                json_encode(self::EXPECTED_FILENAMES, JSON_THROW_ON_ERROR)
            ));
        }
    }
}
