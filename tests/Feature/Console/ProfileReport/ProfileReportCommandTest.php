<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ProfileReport;

use Gacela\Console\Infrastructure\Command\ProfileReportCommand;
use Gacela\Framework\Profiler\Profiler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_map;
use function array_slice;
use function explode;
use function json_decode;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_repeat;

use function usleep;

use const JSON_THROW_ON_ERROR;

final class ProfileReportCommandTest extends TestCase
{
    /**
     * Allocated while the second operation is running so its `memory_get_usage(true)`
     * sample is measurably above the first one's, which is what makes `--sort=memory`
     * observable.
     */
    private const BLOB_BYTES = 24 * 1024 * 1024;

    private Profiler $profiler;

    protected function setUp(): void
    {
        $this->profiler = Profiler::getInstance();
        $this->profiler->reset();
        $this->profiler->enable();
    }

    protected function tearDown(): void
    {
        $this->profiler->disable();
        $this->profiler->reset();
    }

    public function test_help_describes_manual_instrumentation_not_automatic_tracking(): void
    {
        $help = (new ProfileReportCommand())->getHelp();

        // The framework does not instrument itself, so the help must not
        // promise automatic tracking (which always yields an empty report).
        self::assertStringNotContainsString('automatically tracked', $help);

        // It must show a concrete manual start()/stop() example instead.
        self::assertStringContainsString("\$profiler->start('db-query', 'users')", $help);
        self::assertStringContainsString("\$profiler->stop('db-query', 'users')", $help);
    }

    public function test_reports_that_the_profiler_is_disabled(): void
    {
        $this->profiler->disable();

        $tester = $this->runCommand([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'Profiler is not enabled. Enable it with Profiler::getInstance()->enable()',
            '',
            '',
        ], self::linesOf($tester->getDisplay()));
    }

    public function test_reports_that_there_is_no_profiling_data(): void
    {
        $tester = $this->runCommand([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'No profiling data available',
            '',
            '',
        ], self::linesOf($tester->getDisplay()));
    }

    public function test_table_format_renders_every_entry_and_the_summary(): void
    {
        $this->recordTwoOperations();

        $tester = $this->runCommand([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Performance Profiling Report',
            str_repeat('=', 60),
            '',
            '┌───────────┬──────────────┬───────────────┬─────────────┐',
            '│ Operation │ Subject │ Duration (ms) │ Memory (KB) │',
            '├───────────┼──────────────┼───────────────┼─────────────┤',
            $this->expectedRow(0),
            $this->expectedRow(1),
            '└───────────┴──────────────┴───────────────┴─────────────┘',
            '',
            ...$this->expectedSummaryLines(),
        ], self::linesOf($tester->getDisplay()));
    }

    public function test_summary_format_renders_only_the_summary(): void
    {
        $this->recordTwoOperations();

        $tester = $this->runCommand(['--format' => 'summary']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame($this->expectedSummaryLines(), self::linesOf($tester->getDisplay()));
    }

    public function test_json_format_renders_pretty_printed_entries_and_stats(): void
    {
        $this->recordTwoOperations();

        $tester = $this->runCommand(['--format' => 'json']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringStartsWith("{\n    \"entries\": [\n", $display, $display);

        $lines = explode("\n", $display);
        self::assertSame(['}', '', ''], array_slice($lines, -3));

        /** @var array{entries: list<array<string, mixed>>, stats: array<string, mixed>} $decoded */
        $decoded = json_decode($display, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['entries', 'stats'], array_keys($decoded));
        self::assertSame($this->profiler->getStats(), $decoded['stats']);

        $entries = $this->profiler->getEntries();
        self::assertSame([
            [
                'operation' => $entries[0]->operation,
                'subject' => $entries[0]->subject,
                'start_time' => $entries[0]->startTime,
                'end_time' => $entries[0]->endTime,
                'duration' => $entries[0]->duration,
                'memory_usage' => $entries[0]->memoryUsage,
            ],
            [
                'operation' => $entries[1]->operation,
                'subject' => $entries[1]->subject,
                'start_time' => $entries[1]->startTime,
                'end_time' => $entries[1]->endTime,
                'duration' => $entries[1]->duration,
                'memory_usage' => $entries[1]->memoryUsage,
            ],
        ], $decoded['entries']);
    }

    public function test_entries_are_sorted_by_duration_descending_by_default(): void
    {
        $this->recordTwoOperations();

        self::assertSame(['zeta-op', 'alpha-op'], $this->operationsFromJson([]));
    }

    public function test_entries_can_be_sorted_by_memory_descending(): void
    {
        $this->recordTwoOperations();

        $entries = $this->profiler->getEntries();
        self::assertGreaterThan($entries[0]->memoryUsage, $entries[1]->memoryUsage);

        self::assertSame(['alpha-op', 'zeta-op'], $this->operationsFromJson(['--sort' => 'memory']));
    }

    public function test_entries_can_be_sorted_by_operation_name_ascending(): void
    {
        $this->recordTwoOperations();

        self::assertSame(['alpha-op', 'zeta-op'], $this->operationsFromJson(['--sort' => 'operation']));
    }

    /**
     * @param array<string, string> $input
     */
    private function runCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new ProfileReportCommand());
        $tester->execute($input);

        return $tester;
    }

    /**
     * @param array<string, string> $input
     *
     * @return list<string>
     */
    private function operationsFromJson(array $input): array
    {
        $tester = $this->runCommand($input + ['--format' => 'json']);

        /** @var array{entries: list<array{operation: string}>} $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        return array_map(
            static fn (array $entry): string => $entry['operation'],
            $decoded['entries'],
        );
    }

    /**
     * The first operation is the slow one, the second the memory-hungry one, so
     * every sort key produces a different order.
     */
    private function recordTwoOperations(): void
    {
        $this->profiler->start('zeta-op', 'slow-subject');
        usleep(5000);
        $this->profiler->stop('zeta-op', 'slow-subject');

        $blob = str_repeat('x', self::BLOB_BYTES);
        $this->profiler->start('alpha-op', 'fast-subject');
        usleep(1000);
        $this->profiler->stop('alpha-op', 'fast-subject');
        self::assertNotSame('', $blob);
        unset($blob);
    }

    private function expectedRow(int $index): string
    {
        $entry = $this->profiler->getEntries()[$index];

        return sprintf(
            '│ %s │ %s │ %s │ %s │',
            $entry->operation,
            $entry->subject,
            self::milliseconds($entry->duration),
            sprintf('%.2f', $entry->memoryUsage / 1024),
        );
    }

    /**
     * @return list<string>
     */
    private function expectedSummaryLines(): array
    {
        $stats = $this->profiler->getStats();

        $lines = [
            'Summary Statistics',
            sprintf('Total Operations: %d', $stats['total_operations']),
            sprintf('Total Duration: %s ms', self::milliseconds($stats['total_duration'])),
            sprintf('Average Duration: %s ms', self::milliseconds($stats['avg_duration'])),
            sprintf('Peak Memory: %.2f KB', $stats['peak_memory'] / 1024),
            '',
            'By Operation:',
            'Operation Count Total (ms) Avg (ms)',
        ];

        foreach ($stats['by_operation'] as $operation => $operationStats) {
            $lines[] = sprintf(
                '%s %d %s %s',
                $operation,
                $operationStats['count'],
                self::milliseconds($operationStats['total_duration']),
                self::milliseconds($operationStats['avg_duration']),
            );
        }

        $lines[] = '';
        $lines[] = '';

        return $lines;
    }

    private static function milliseconds(float $seconds): string
    {
        return sprintf('%.3f', $seconds * 1000.0);
    }

    /**
     * Collapses the column padding the console tables add, so the assertions
     * describe the report's content and blank lines without depending on how
     * wide any single value happens to render.
     *
     * @return list<string>
     */
    private static function linesOf(string $display): array
    {
        return array_map(
            static fn (string $line): string => (string)preg_replace('/ {2,}/', ' ', rtrim($line)),
            explode("\n", $display),
        );
    }
}
