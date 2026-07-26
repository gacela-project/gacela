<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ProfileReport;

use Gacela\Console\Infrastructure\Command\ProfileReportCommand;
use Gacela\Framework\Profiler\Profiler;
use Gacela\Framework\Profiler\TProfileEntry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_keys;
use function array_map;
use function json_decode;
use function sprintf;
use function str_repeat;
use function usleep;
use function usort;

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
        self::assertStringContainsString('Profiler is not enabled', $tester->getDisplay());
    }

    public function test_reports_that_there_is_no_profiling_data(): void
    {
        $tester = $this->runCommand([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No profiling data available', $tester->getDisplay());
    }

    public function test_table_format_reports_every_recorded_entry_and_the_summary(): void
    {
        $this->recordTwoOperations();

        $tester = $this->runCommand([]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        foreach ($this->profiler->getEntries() as $entry) {
            self::assertStringContainsString($entry->operation, $display);
            self::assertStringContainsString($entry->subject, $display);
        }

        self::assertStringContainsString('Summary Statistics', $display);
        self::assertStringContainsString(
            sprintf('Total Operations: %d', $this->profiler->getStats()['total_operations']),
            $display,
        );
    }

    public function test_summary_format_drops_the_per_entry_rows(): void
    {
        $this->recordTwoOperations();

        $tester = $this->runCommand(['--format' => 'summary']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Summary Statistics', $display);

        // Subjects only ever appear in the per-entry table, so their absence is
        // what distinguishes --format=summary from the default report.
        self::assertStringNotContainsString('slow-subject', $display);
        self::assertStringNotContainsString('fast-subject', $display);
    }

    public function test_json_format_reports_the_recorded_entries_and_stats(): void
    {
        $this->recordTwoOperations();

        $tester = $this->runCommand(['--format' => 'json']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        /** @var array{entries: list<array<string, mixed>>, stats: array<string, mixed>} $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['entries', 'stats'], array_keys($decoded));
        self::assertSame($this->profiler->getStats(), $decoded['stats']);

        $expected = array_map(
            static fn (TProfileEntry $entry): array => [
                'operation' => $entry->operation,
                'subject' => $entry->subject,
                'start_time' => $entry->startTime,
                'end_time' => $entry->endTime,
                'duration' => $entry->duration,
                'memory_usage' => $entry->memoryUsage,
            ],
            $this->entriesSortedByDurationDesc(),
        );

        self::assertSame($expected, $decoded['entries']);
    }

    public function test_entries_are_sorted_by_duration_descending_by_default(): void
    {
        $this->recordTwoOperations();

        $expected = array_map(
            static fn (TProfileEntry $entry): string => $entry->operation,
            $this->entriesSortedByDurationDesc(),
        );

        // Guard the assertion below: with equal durations any order would pass.
        [$slower, $faster] = $this->entriesSortedByDurationDesc();
        self::assertGreaterThan($faster->duration, $slower->duration);

        self::assertSame($expected, $this->operationsFromJson([]));
    }

    public function test_entries_can_be_sorted_by_memory_descending(): void
    {
        $this->recordTwoOperations();

        $entries = $this->profiler->getEntries();
        // The blob allocated in recordTwoOperations() makes the second entry heavier.
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
     * The recorded durations come from real sleeps, and timer granularity differs
     * per platform (Windows is coarse enough to invert a 5ms/1ms gap). Deriving the
     * expected order from what was actually recorded keeps the assertion about the
     * command's sorting rather than about the host's clock.
     *
     * @return list<TProfileEntry>
     */
    private function entriesSortedByDurationDesc(): array
    {
        $entries = $this->profiler->getEntries();
        usort($entries, static fn (TProfileEntry $a, TProfileEntry $b): int => $b->duration <=> $a->duration);

        return $entries;
    }

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
}
