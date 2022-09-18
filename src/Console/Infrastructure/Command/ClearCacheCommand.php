<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\DocBlockResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function count;

/**
 * @method ConsoleFacade getFacade()
 */
final class ClearCacheCommand extends Command
{
    use DocBlockResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('clear:cache')
            ->setDescription('Clear all gacela cache files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $removedFilenames = $this->getFacade()->clearCacheFile();

        if (count($removedFilenames) === 0) {
            $output->writeln('<fg=yellow>No gacela cache files found.</>');
        } else {
            $output->writeln('<fg=green>Gacela cache files cleared successfully:</>');
            foreach ($removedFilenames as $filename) {
                $output->writeln("> {$filename}");
            }
        }

        return self::SUCCESS;
    }
}
