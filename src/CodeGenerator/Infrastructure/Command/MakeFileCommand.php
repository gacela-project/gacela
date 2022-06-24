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
final class MakeFileCommand extends Command
{
    use DocBlockResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('make:file')
            ->setDescription('Generate a ' . $this->getExpectedFilenames())
            ->addArgument('path', InputArgument::REQUIRED, 'The file path. For example "App/TestModule/TestSubModule"')
            ->addArgument('filenames', InputArgument::REQUIRED | InputArgument::IS_ARRAY, $this->getExpectedFilenames())
            ->addOption('short-name', 's', InputOption::VALUE_NONE, 'Remove module prefix to the class name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $inputFileNames */
        $inputFileNames = $input->getArgument('filenames');

        $filenames = array_map(
            fn (string $raw): string => $this->getFacade()->sanitizeFilename($raw),
            $inputFileNames
        );

        /** @var string $path */
        $path = $input->getArgument('path');
        $commandArguments = $this->getFacade()->parseArguments($path);
        $shortName = (bool)$input->getOption('short-name');

        foreach ($filenames as $filename) {
            $absolutePath = $this->getFacade()->generateFileContent(
                $commandArguments,
                $filename,
                $shortName
            );
            $output->writeln("> Path '{$absolutePath}' created successfully");
        }

        return self::SUCCESS;
    }

    private function getExpectedFilenames(): string
    {
        return implode(', ', FilenameSanitizer::EXPECTED_FILENAMES);
    }
}
