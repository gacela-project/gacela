<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\ServiceMapMigration\MigrationResult;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class MigrateServiceMapCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('migrate:service-map')
            ->setDescription('Declare every @method pillar accessor with #[ServiceMap], for 3.0')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Only files whose path contains this', '')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change and write nothing')
            ->setHelp($this->getHelpText());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = ConsoleInput::argument($input, 'filter');
        $dryRun = $input->getOption('dry-run') === true;

        ConsoleSection::title($output, 'Migrate @method accessors to #[ServiceMap]');

        $results = $this->getFacade()->migrateServiceMaps($filter, $dryRun);

        if ($results === []) {
            $output->writeln('<fg=green>✓ Nothing to migrate</>');
            $output->writeln(
                'Every pillar accessor resolved from a `@method` docblock already declares its'
                . ' attribute, or the file declares none.',
            );

            return Command::SUCCESS;
        }

        foreach ($results as $result) {
            $this->report($result, $output);
        }

        $output->writeln('');
        ConsoleSection::separator($output);
        $output->writeln(sprintf(
            '%s %d accessor(s) in %d file(s)',
            $dryRun ? '<comment>Would declare</comment>' : '<fg=green>Declared</>',
            $this->accessorCount($results),
            count($results),
        ));

        if ($dryRun) {
            $output->writeln('Nothing was written. Run without <comment>--dry-run</comment> to apply.');
        }

        return Command::SUCCESS;
    }

    private function report(MigrationResult $result, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln(sprintf('<info>%s</info>', $result->path));

        foreach ($result->declared as $accessor) {
            $output->writeln(sprintf('    %s', $accessor));
        }
    }

    /**
     * @param list<MigrationResult> $results
     */
    private function accessorCount(array $results): int
    {
        $total = 0;

        foreach ($results as $result) {
            $total += $result->declaredCount();
        }

        return $total;
    }

    private function getHelpText(): string
    {
        return <<<'TXT'
            Resolving a pillar accessor from its <comment>@method</comment> docblock is deprecated in 2.x
            and removed in 3.0. The static analysis rule <comment>gacela.serviceMapMissing</comment> reports
            each one and prints the exact attribute to declare it with; this writes that
            attribute, for every class at once.

            The runtime deprecation only fires for accessors a run actually reaches, and
            only on a cold resolve, so a migration driven by notices covers the code paths
            your tests happen to execute. This reads the source instead.

            Only the attribute line and, when missing, the <comment>ServiceMap</comment> import are added.
            Nothing else in the file moves, and running it twice changes nothing.

            An accessor whose <comment>@method</comment> type is not a single class name -- a union, a
            nullable, a generic -- is left for a human: there is no <comment>X::class</comment> to write.
            TXT;
    }
}
