<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Framework\Profiler\Profiler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;
use function usort;

final class ProfileReportCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('profile:report')
            ->setDescription('Display performance profiling report')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format: table, json, summary',
                'table',
            )
            ->addOption(
                'sort',
                's',
                InputOption::VALUE_REQUIRED,
                'Sort by: duration, memory, operation',
                'duration',
            )
            ->setHelp($this->getHelpText());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profiler = Profiler::getInstance();

        if (!$profiler->isEnabled()) {
            $output->writeln('<comment>Profiler is not enabled. Enable it with Profiler::getInstance()->enable()</comment>');
            $output->writeln('');

            return self::SUCCESS;
        }

        $entries = $profiler->getEntries();

        if ($entries === []) {
            $output->writeln('<info>No profiling data available</info>');
            $output->writeln('');

            return self::SUCCESS;
        }

        $format = (string)$input->getOption('format');
        $sortBy = (string)$input->getOption('sort');

        // Sort entries
        $entries = $this->sortEntries($entries, $sortBy);

        match ($format) {
            'json' => $this->outputJson($entries, $profiler, $output),
            'summary' => $this->outputSummary($profiler, $output),
            default => $this->outputTable($entries, $profiler, $output),
        };

        return self::SUCCESS;
    }

    /**
     * @param list<\Gacela\Framework\Profiler\TProfileEntry> $entries
     *
     * @return list<\Gacela\Framework\Profiler\TProfileEntry>
     */
    private function sortEntries(array $entries, string $sortBy): array
    {
        usort($entries, static fn ($a, $b): int => match ($sortBy) {
            'memory' => $b->memoryUsage <=> $a->memoryUsage,
            'operation' => $a->operation <=> $b->operation,
            default => $b->duration <=> $a->duration,
        });

        return $entries;
    }

    /**
     * @param list<\Gacela\Framework\Profiler\TProfileEntry> $entries
     */
    private function outputTable(array $entries, Profiler $profiler, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>Performance Profiling Report</info>');
        $output->writeln(sprintf('<info>%s</info>', str_repeat('=', 60)));
        $output->writeln('');

        $table = new Table($output);
        $table->setStyle('box');
        $table->setHeaders(['Operation', 'Subject', 'Duration (ms)', 'Memory (KB)']);

        foreach ($entries as $entry) {
            $table->addRow([
                $entry->operation,
                $entry->subject,
                sprintf('%.3f', $entry->duration * 1000),
                sprintf('%.2f', $entry->memoryUsage / 1024),
            ]);
        }

        $table->render();
        $output->writeln('');

        $this->outputSummary($profiler, $output);
    }

    /**
     * @param list<\Gacela\Framework\Profiler\TProfileEntry> $entries
     */
    private function outputJson(array $entries, Profiler $profiler, OutputInterface $output): void
    {
        $data = [
            'entries' => [],
            'stats' => $profiler->getStats(),
        ];

        foreach ($entries as $entry) {
            $data['entries'][] = [
                'operation' => $entry->operation,
                'subject' => $entry->subject,
                'start_time' => $entry->startTime,
                'end_time' => $entry->endTime,
                'duration' => $entry->duration,
                'memory_usage' => $entry->memoryUsage,
            ];
        }

        $output->writeln((string)json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $output->writeln('');
    }

    private function outputSummary(Profiler $profiler, OutputInterface $output): void
    {
        $stats = $profiler->getStats();

        $output->writeln('<comment>Summary Statistics</comment>');
        $output->writeln(sprintf('Total Operations: <info>%d</info>', $stats['total_operations']));
        $output->writeln(sprintf('Total Duration: <info>%.3f ms</info>', $stats['total_duration'] * 1000));
        $output->writeln(sprintf('Average Duration: <info>%.3f ms</info>', $stats['avg_duration'] * 1000));
        $output->writeln(sprintf('Peak Memory: <info>%.2f KB</info>', $stats['peak_memory'] / 1024));
        $output->writeln('');

        if ($stats['by_operation'] !== []) {
            $output->writeln('<comment>By Operation:</comment>');
            $table = new Table($output);
            $table->setStyle('compact');
            $table->setHeaders(['Operation', 'Count', 'Total (ms)', 'Avg (ms)']);

            foreach ($stats['by_operation'] as $operation => $opStats) {
                $table->addRow([
                    $operation,
                    $opStats['count'],
                    sprintf('%.3f', $opStats['total_duration'] * 1000),
                    sprintf('%.3f', $opStats['avg_duration'] * 1000),
                ]);
            }

            $table->render();
            $output->writeln('');
        }
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
Display performance profiling information for Gacela operations.

<info>Usage:</info>

1. Enable profiling in your bootstrap:
```php
use Gacela\Framework\Profiler\Profiler;

Profiler::getInstance()->enable();
```

2. Profiling is automatically tracked for:
- Module resolution
- Service resolution
- Container operations
- Factory creation

3. View the profiling report:
```bash
bin/gacela profile:report
```

<info>Options:</info>
  <comment>--format</comment>  Output format
    - table: Human-readable table (default)
    - json: JSON format for programmatic processing
    - summary: Summary statistics only

  <comment>--sort</comment>  Sort entries by
    - duration: Slowest operations first (default)
    - memory: Highest memory usage first
    - operation: Alphabetically by operation type

<info>Examples:</info>
  # View detailed table
  bin/gacela profile:report

  # View only summary
  bin/gacela profile:report --format=summary

  # Sort by memory usage
  bin/gacela profile:report --sort=memory

  # Export as JSON
  bin/gacela profile:report --format=json > profile.json
HELP;
    }
}
