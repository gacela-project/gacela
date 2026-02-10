<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;
use function usleep;

/**
 * @method ConsoleFacade getFacade()
 */
final class DevWatchCommand extends Command
{
    use ServiceResolverAwareTrait; // 1 second in microseconds

    protected function configure(): void
    {
        $this->setName('dev:watch')
            ->setDescription('Watch for file changes and auto-rebuild caches')
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Paths to watch for changes',
                ['src'],
            )
            ->addOption(
                'interval',
                'i',
                InputOption::VALUE_REQUIRED,
                'Check interval in milliseconds',
                '1000',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $watchPaths */
        $watchPaths = $input->getOption('path');
        /** @var int<1, max> $intervalOption */
        $intervalOption = $input->getOption('interval');
        $interval = $intervalOption * 1000; // Convert ms to microseconds

        $output->writeln('<info>Starting file watcher...</info>');
        $output->writeln(sprintf('<comment>Watching paths: %s</comment>', implode(', ', $watchPaths)));
        $output->writeln('<comment>Press Ctrl+C to stop</comment>');
        $output->writeln('');

        $this->getFacade()->initializeFileWatcher($watchPaths);

        while (true) {
            $changedFiles = $this->getFacade()->detectFileChanges($watchPaths);

            if (count($changedFiles) > 0) {
                $output->writeln(sprintf(
                    '<fg=yellow>[%s]</> Detected %d file change(s)',
                    date('H:i:s'),
                    count($changedFiles),
                ));

                foreach ($changedFiles as $file) {
                    $output->writeln(sprintf('  - %s', $file));
                }

                $output->writeln('<info>Clearing caches...</info>');
                $this->getFacade()->clearDevelopmentCaches();
                $output->writeln('<info>✓ Caches cleared successfully</info>');
                $output->writeln('');
            }

            usleep($interval);
        }
    }
}
