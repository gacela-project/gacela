<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\CommandArguments\ModulePath;
use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Output\OutputInterface;

use function implode;
use function in_array;
use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class MakeModuleCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const TEMPLATES = ['basic', 'service', 'minimal'];

    protected function configure(): void
    {
        $this->setName('make:module')
            ->setDescription('Generate a basic module with an empty ' . FilenameSanitizer::expectedFilenamesAsText())
            ->addArgument('path', InputArgument::REQUIRED, 'The file path. For example "App/TestModule/TestSubModule"')
            ->addOption('short-name', 's', InputOption::VALUE_NONE, 'Remove module prefix to the class name')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Module template: basic, service (Facade wired to a Domain service), or minimal (Facade + Factory only)', 'basic')
            ->addOption('minimal', null, InputOption::VALUE_NONE, 'Scaffold only the Facade and Factory pillars (shorthand for --template=minimal)')
            ->addOption('with-tests', null, InputOption::VALUE_NONE, 'Also scaffold a facade test (service template only)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Replace files that already exist')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report the files that would be written, and write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $template = ConsoleInput::option($input, 'template');
        if ($input->getOption('minimal') === true) {
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

        // Before anything is written: a segment that is not a PHP label makes
        // both the namespace and the class name unparseable, and a half-written
        // module is worse than a refusal.
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
        $isService = $template === 'service';
        $withTests = $input->getOption('with-tests') === true;

        // Only the service template scaffolds a facade test, and accepting the
        // flag on the others wrote four files, reported "created successfully"
        // and produced no test -- so a reader who asked for one believes they
        // have it. Refused rather than ignored, the same way an unusable path
        // is.
        if ($withTests && !$isService) {
            $output->writeln(sprintf(
                '<error>--with-tests only applies to the service template, and the "%s" template scaffolds no test.</error>',
                $template,
            ));
            $output->writeln('Add <comment>--template=service</comment>, or drop <comment>--with-tests</comment>.');

            return self::FAILURE;
        }

        $files = $this->filesFor($template, $withTests);

        // Every target, before the first one is written. Generating over a
        // module replaces hand-written code with a stub and reports it as
        // "created", so a partly-replaced module is the one outcome there is
        // no way back from -- the same reason an unusable path is refused
        // before generation rather than during it.
        if ($input->getOption('force') !== true) {
            $existing = $this->getFacade()->existingGeneratedFiles($commandArguments, $files, $shortName);

            if ($existing !== []) {
                ConsoleSection::refusedToOverwrite($output, $existing);

                return self::FAILURE;
            }
        }

        // After the refusal above, not before it: a preview is only worth
        // reading if it predicts the run it previews, and a run without --force
        // over an existing module refuses rather than writing.
        if ($input->getOption('dry-run') === true) {
            ConsoleSection::plannedFiles(
                $output,
                $this->getFacade()->plannedGeneratedFiles($commandArguments, $files, $shortName),
            );

            return self::SUCCESS;
        }

        foreach ($files as [$filename, $subDirectory]) {
            $fullPath = $isService
                ? $this->getFacade()->generateServiceFileContent($commandArguments, $filename, $shortName, $subDirectory)
                : $this->getFacade()->generateFileContent($commandArguments, $filename, $shortName);

            $output->writeln(sprintf("> Path '%s' created successfully", $fullPath));
        }

        $pieces = explode('/', $commandArguments->directory());
        $moduleName = end($pieces);
        $output->writeln(sprintf("Module '%s' created successfully", $moduleName));

        return self::SUCCESS;
    }

    /**
     * Which files a template scaffolds, as [filename, subDirectory] pairs.
     *
     * One list per template, read once to check what exists and again to
     * write: two lists would let the check and the writing disagree, which is
     * precisely the disagreement that loses a file.
     *
     * @return list<array{string, string}>
     */
    private function filesFor(string $template, bool $withTests): array
    {
        if ($template === 'minimal') {
            // Config and Provider are optional: add them when the module
            // actually reads config or wires external dependencies.
            return [[FilenameSanitizer::FACADE, ''], [FilenameSanitizer::FACTORY, '']];
        }

        $files = [];
        foreach (FilenameSanitizer::EXPECTED_FILENAMES as $filename) {
            $files[] = [$filename, ''];
        }

        if ($template !== 'service') {
            return $files;
        }

        $files[] = ['Service', 'Domain'];
        if ($withTests) {
            $files[] = ['FacadeTest', 'Tests'];
        }

        return $files;
    }
}
