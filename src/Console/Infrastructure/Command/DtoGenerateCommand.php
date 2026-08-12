<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\DtoGenerate\DtoGenerateResult;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class DtoGenerateCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('dto:generate')
            ->setDescription('Generate the immutable DTO classes declared with declareDtoSchema()')
            ->setHelp($this->getHelpText())
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run') === true;
        $result = $this->getFacade()->generateDtoClasses($dryRun);

        if ($result->total() === 0) {
            $output->writeln('<comment>No shape declared. Use $config->declareDtoSchema(...) in gacela.php.</comment>');

            return Command::SUCCESS;
        }

        $this->writeWritten($result, $dryRun, $output);
        $this->writeUnplaceable($result, $output);

        // An unplaceable shape is the one failure this command can have: the
        // declaration is fine and there is simply nowhere the class may live.
        return $result->unplaceable === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function writeWritten(DtoGenerateResult $result, bool $dryRun, OutputInterface $output): void
    {
        foreach ($result->written as $file) {
            $output->writeln(sprintf(
                '<info>%s</info> %s',
                $dryRun ? 'would write' : 'wrote',
                $file,
            ));
        }

        if ($result->unchanged !== []) {
            $output->writeln(sprintf('<comment>%d class(es) already up to date</comment>', count($result->unchanged)));
        }
    }

    private function writeUnplaceable(DtoGenerateResult $result, OutputInterface $output): void
    {
        foreach ($result->unplaceable as $className) {
            $output->writeln(sprintf(
                '<error>No composer autoload prefix covers %s</error>',
                $className,
            ));
        }

        if ($result->unplaceable !== []) {
            $output->writeln('');
            $output->writeln('Add a psr-4 prefix for that namespace to your composer.json autoload, so the generated class lands where your autoloader already looks.');
        }
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
Writes one final, fully typed php class per shape declared with
<info>declareDtoSchema()</info> — typed getters, <info>with*()</info> copies, <info>toArray()</info> and <info>fromArray()</info>.

<info>Where the files go</info>
  Each shape is declared by the class it generates, so the file is written where
  your own composer `autoload` already looks for that namespace. Nothing
  registers an autoloader, and static analysis reads the classes without this
  command having run first.

<info>Merging</info>
  Every declarer of one shape contributes to it: a package declares `Order`, your
  project adds a property to the same class, and both land in one generated file.
  Redeclaring a property differently is refused at bootstrap, because the package
  that declared it first reads the same class.

<info>Regenerating</info>
  Output is byte-identical for an unchanged declaration, so a repeat run leaves
  version control quiet. The files are derived — do not edit them.

<info>Examples:</info>
  bin/gacela dto:generate
  bin/gacela dto:generate --dry-run
HELP;
    }
}
