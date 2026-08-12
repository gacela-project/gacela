<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\IdeMeta\IdeMetadataResult;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function implode;
use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class IdeMetaCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('ide:meta')
            ->setDescription('Generate editor metadata for getProvidedDependency() from the #[Provides] attributes')
            ->setHelp($this->getHelpText())
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run') === true;
        $result = $this->getFacade()->generateIdeMetadata($dryRun);

        $this->writeAmbiguous($result, $output);
        $this->writeOutcome($result, $dryRun, $output);

        return Command::SUCCESS;
    }

    /**
     * An id two providers register with different types is reported here rather
     * than written, because the editor map is application-wide while
     * `getProvidedDependency()` reads the calling module's container. Silence
     * would leave the id looking simply unsupported.
     */
    private function writeAmbiguous(IdeMetadataResult $result, OutputInterface $output): void
    {
        if ($result->ambiguous === []) {
            return;
        }

        $output->writeln('<comment>Ids left untyped, registered with more than one type:</comment>');

        foreach ($result->ambiguous as $id => $classNames) {
            $output->writeln(sprintf('  %s: %s', $id, implode(', ', $classNames)));
        }

        $output->writeln('');
    }

    private function writeOutcome(IdeMetadataResult $result, bool $dryRun, OutputInterface $output): void
    {
        $summary = sprintf('%d string id(s) typed', $result->typedIds);

        if (!$result->changed) {
            $output->writeln(sprintf('<info>%s is up to date</info> (%s)', $result->path, $summary));
            return;
        }

        if ($dryRun) {
            $output->writeln(sprintf('<comment>%s would change</comment> (%s)', $result->path, $summary));
            return;
        }

        $output->writeln(sprintf('<info>Wrote %s</info> (%s)', $result->path, $summary));
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
Writes a PhpStorm advanced-metadata file so the editor types
<info>$this->getProvidedDependency(...)</info>, which the method signature leaves as `mixed`.

<info>What it writes:</info>
  - An id naming a class or interface resolves to it — the rule PHPStan and
    Psalm already apply, stated once and needing no rescan.
  - Each string id, typed by the return type of the #[Provides] method that
    registers it. Neither analyser types these, by design.

<info>What it does not write:</info>
  - Ids two providers register with different types. The map is keyed by
    argument value across the whole application while the call reads one
    module's container, so one entry would be wrong somewhere. They are listed
    instead.
  - Ids whose provider method returns `array`, a nullable or a union: a map
    value is a class name, and there is nothing truthful to put there.

<info>The file</info>
  <comment>.phpstorm.meta.php/gacela.meta.php</comment>, in the project, because that is where an
  editor looks. It is generated — add it to .gitignore and regenerate after
  changing a Provider. `doctor` reports when it no longer matches.

<info>Examples:</info>
  bin/gacela ide:meta
  bin/gacela ide:meta --dry-run
HELP;
    }
}
