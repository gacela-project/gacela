<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\CommandArguments\ModulePath;
use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use Gacela\Framework\ClassResolver\ResolvableTypes;
use Gacela\Framework\ServiceResolver\ServiceMap;
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
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class MakeFileCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        // The declarations are read here, not through the facade: `configure()`
        // runs from the constructor, and the provider that constructs this
        // command is itself being resolved at that moment.
        $filenames = FilenameSanitizer::expectedFilenamesAsText(ResolvableTypes::declaredKinds());

        $this->setName('make:file')
            ->setDescription('Generate a ' . $filenames)
            ->addArgument('path', InputArgument::REQUIRED, 'The file path. For example "App/TestModule/TestSubModule"')
            ->addArgument('filenames', InputArgument::REQUIRED | InputArgument::IS_ARRAY, $filenames)
            ->addOption('short-name', 's', InputOption::VALUE_NONE, 'Remove module prefix to the class name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $inputFileNames */
        $inputFileNames = $input->getArgument('filenames');

        $filenames = array_map(
            fn (string $raw): string => $this->getFacade()->sanitizeFilename($raw),
            $inputFileNames,
        );

        /** @var string $path */
        $path = $input->getArgument('path');

        // Same rule as make:module, and for the same reason: a segment that is
        // not a PHP label makes the namespace and the class name unparseable,
        // and the files are written before anyone finds out.
        $unusable = ModulePath::firstUnusableSegment($path);
        if ($unusable !== null) {
            $output->writeln(sprintf(
                '<error>"%s" cannot be part of a module path: "%s" is not a valid PHP name.</error>',
                $path,
                $unusable,
            ));
            $output->writeln('Every segment becomes a namespace and a class name -- use <comment>UserProfile</comment>, not <comment>user-profile</comment>.');

            return self::FAILURE;
        }

        $commandArguments = $this->getFacade()->parseArguments($path);
        $shortName = $input->getOption('short-name') === true;

        foreach ($filenames as $filename) {
            $absolutePath = $this->getFacade()->generateFileContent(
                $commandArguments,
                $filename,
                $shortName,
            );
            $output->writeln(sprintf("> Path '%s' created successfully", $absolutePath));
        }

        return self::SUCCESS;
    }
}
