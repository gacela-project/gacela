<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Domain\Deprecation\DeprecationScanner;
use Gacela\Console\Domain\Deprecation\TDeprecationInfo;
use Gacela\Framework\Gacela;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Throwable;

use function array_filter;
use function count;
use function sprintf;
use function str_replace;
use function usort;

final class ListDeprecatedCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('list:deprecated')
            ->setDescription('List all deprecated classes, methods, and properties in the codebase')
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED,
                'Output format: table, json, or markdown',
                'table',
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Filter by type: class, method, property, constant',
            )
            ->addOption(
                'will-remove-in',
                'r',
                InputOption::VALUE_REQUIRED,
                'Filter by removal version (e.g., 2.0)',
            )
            ->setHelp($this->getHelpText());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>Scanning for Deprecated Items</info>');
        $output->writeln(sprintf('<info>%s</info>', str_repeat('=', 60)));
        $output->writeln('');

        try {
            $scanner = new DeprecationScanner(Gacela::rootDir());
            $deprecations = $scanner->scan();

            // Apply filters
            $typeFilter = $input->getOption('type');
            if ($typeFilter !== null) {
                $deprecations = array_filter(
                    $deprecations,
                    static fn (TDeprecationInfo $info): bool => $info->elementType === $typeFilter,
                );
            }

            $willRemoveInFilter = $input->getOption('will-remove-in');
            if ($willRemoveInFilter !== null) {
                $deprecations = array_filter(
                    $deprecations,
                    static fn (TDeprecationInfo $info): bool => $info->willRemoveIn === $willRemoveInFilter,
                );
            }

            $deprecations = array_values($deprecations);

            if ($deprecations === []) {
                $output->writeln('<fg=green>✓ No deprecated items found!</fg=green>');
                $output->writeln('');

                return self::SUCCESS;
            }

            // Sort by element name
            usort($deprecations, static fn (TDeprecationInfo $a, TDeprecationInfo $b): int => $a->elementName <=> $b->elementName);

            $output->writeln(sprintf('Found <comment>%d</comment> deprecated item%s:', count($deprecations), count($deprecations) === 1 ? '' : 's'));
            $output->writeln('');

            $format = (string)$input->getOption('format');

            match ($format) {
                'json' => $this->outputJson($deprecations, $output),
                'markdown' => $this->outputMarkdown($deprecations, $output),
                default => $this->outputTable($deprecations, $output),
            };

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>Error: %s</error>', $throwable->getMessage()));
            $output->writeln('');

            return self::FAILURE;
        }
    }

    /**
     * @param list<TDeprecationInfo> $deprecations
     */
    private function outputTable(array $deprecations, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setStyle('box');
        $table->setHeaders(['Element', 'Type', 'Since', 'Will Remove', 'Replacement', 'Location']);

        foreach ($deprecations as $deprecation) {
            $location = $this->formatLocation($deprecation->file, $deprecation->line);

            $table->addRow([
                $deprecation->elementName,
                $deprecation->elementType,
                $deprecation->since,
                $deprecation->willRemoveIn ?? '-',
                $deprecation->replacement ?? '-',
                $location,
            ]);
        }

        $table->render();
        $output->writeln('');
    }

    /**
     * @param list<TDeprecationInfo> $deprecations
     */
    private function outputJson(array $deprecations, OutputInterface $output): void
    {
        $data = [];

        foreach ($deprecations as $deprecation) {
            $data[] = [
                'element' => $deprecation->elementName,
                'type' => $deprecation->elementType,
                'since' => $deprecation->since,
                'will_remove_in' => $deprecation->willRemoveIn,
                'replacement' => $deprecation->replacement,
                'reason' => $deprecation->reason,
                'file' => $deprecation->file,
                'line' => $deprecation->line,
            ];
        }

        $output->writeln((string)json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $output->writeln('');
    }

    /**
     * @param list<TDeprecationInfo> $deprecations
     */
    private function outputMarkdown(array $deprecations, OutputInterface $output): void
    {
        $output->writeln('# Deprecated Items');
        $output->writeln('');
        $output->writeln(sprintf('Total: %d', count($deprecations)));
        $output->writeln('');
        $output->writeln('| Element | Type | Since | Will Remove | Replacement | Location |');
        $output->writeln('|---------|------|-------|-------------|-------------|----------|');

        foreach ($deprecations as $deprecation) {
            $location = $this->formatLocation($deprecation->file, $deprecation->line);

            $output->writeln(sprintf(
                '| %s | %s | %s | %s | %s | %s |',
                $deprecation->elementName,
                $deprecation->elementType,
                $deprecation->since,
                $deprecation->willRemoveIn ?? '-',
                $deprecation->replacement ?? '-',
                $location,
            ));
        }

        $output->writeln('');
    }

    private function formatLocation(string $file, int $line): string
    {
        $relativePath = str_replace(Gacela::rootDir() . '/', '', $file);

        if ($line > 0) {
            return sprintf('%s:%d', $relativePath, $line);
        }

        return $relativePath;
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
This command scans the codebase for items marked with the #[Deprecated] attribute
and generates a report of all deprecated elements.

<info>Usage of #[Deprecated] attribute:</info>

```php
use Gacela\Framework\Attribute\Deprecated;

#[Deprecated(
    since: '1.5.0',
    replacement: 'NewClassName',
    willRemoveIn: '2.0.0',
    reason: 'Use the new implementation for better performance'
)]
class OldClassName
{
    #[Deprecated(
        since: '1.4.0',
        replacement: 'newMethod',
        willRemoveIn: '2.0.0'
    )]
    public function oldMethod(): void
    {
        // ...
    }
}
```

<info>Examples:</info>
  # List all deprecated items in table format
  bin/gacela list:deprecated

  # Filter by type
  bin/gacela list:deprecated --type=method

  # Filter by removal version
  bin/gacela list:deprecated --will-remove-in=2.0.0

  # Output as JSON
  bin/gacela list:deprecated --format=json

  # Output as Markdown
  bin/gacela list:deprecated --format=markdown

<info>Output formats:</info>
  <comment>table</comment>    - Human-readable table (default)
  <comment>json</comment>     - JSON format for programmatic processing
  <comment>markdown</comment> - Markdown table for documentation

<info>Filter options:</info>
  <comment>--type</comment>           - Filter by: class, method, property, constant
  <comment>--will-remove-in</comment> - Filter by removal version (e.g., 2.0.0)
HELP;
    }
}
