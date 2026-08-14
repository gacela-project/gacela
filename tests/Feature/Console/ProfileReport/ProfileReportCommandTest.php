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

        /** @var array{entries: list<array<string, mixed>>, stats: array<string, mixed>, unfinished: array<string, int>} $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['entries', 'stats', 'unfinished'], array_keys($decoded));
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
     * An operation missing from the report because its `stop()` misspelled the
     * subject looks exactly like one that was never instrumented. This is the
     * difference.
     */
    public function test_the_report_names_what_was_started_and_never_stopped(): void
    {
        $this->profiler->start('db-query', 'users');
        $this->profiler->stop('db-query', 'users');
        $this->profiler->start('db-query', 'orders');
        $this->profiler->stop('db-query', 'order');

        $display = $this->runCommand([])->getDisplay();

        self::assertStringContainsString('Started and never stopped:', $display);
        self::assertStringContainsString('db-query:orders', $display);
        self::assertStringContainsString('must match its start()', $display);
    }

    public function test_a_report_with_nothing_open_says_nothing_about_it(): void
    {
        $this->profiler->start('db-query', 'users');
        $this->profiler->stop('db-query', 'users');

        self::assertStringNotContainsString('Started and never stopped', $this->runCommand([])->getDisplay());
    }

    /**
     * Reached even when nothing completed: an unmatched pair is the likeliest
     * reason there is no data to report, and the run that most needs the answer
     * is the one that produced nothing.
     */
    public function test_it_is_reported_even_when_no_entry_completed(): void
    {
        $this->profiler->start('render', 'page');

        $display = $this->runCommand([])->getDisplay();

        self::assertStringContainsString('No profiling data available', $display);
        self::assertStringContainsString('render:page', $display);
    }

    /**
     * The same run as above, asked for as JSON. Both early returns in the
     * command -- profiler off, and nothing recorded -- printed prose whatever
     * `--format` said, and they are the two runs a CI job most often gets: a
     * consumer piping this to a parser got a syntax error rather than a
     * document saying there was nothing.
     *
     * The command already states the rule for the populated path: "Not for
     * `json`, whose consumer parses a document rather than reading prose."
     */
    public function test_json_stays_parseable_when_no_entry_completed(): void
    {
        $this->profiler->start('render', 'page');

        $display = $this->runCommand(['--format' => 'json'])->getDisplay();

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($display, true);

        self::assertIsArray($decoded, 'a --format=json run must emit a document, not prose');
        self::assertSame([], $decoded['entries']);
        // The whole of the report in this run: nothing finished, so the open
        // span is the only thing there is to say.
        self::assertSame(['render:page' => 1], $decoded['unfinished']);
    }

    public function test_json_stays_parseable_when_the_profiler_is_disabled(): void
    {
        $this->profiler->disable();

        $display = $this->runCommand(['--format' => 'json'])->getDisplay();

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($display, true);

        self::assertIsArray($decoded, 'a --format=json run must emit a document, not prose');
        self::assertSame([], $decoded['entries']);
        self::assertSame(0, $decoded['stats']['total_operations']);
    }

    /**
     * The text report keeps its prose. Making every path emit JSON would have
     * been the other way to read this, and it is not the one: a person running
     * the command without `--format` is reading, not parsing.
     */
    public function test_the_text_report_still_says_it_in_words(): void
    {
        $this->profiler->disable();

        self::assertStringContainsString('Profiler is not enabled', $this->runCommand([])->getDisplay());
    }

    /**
     * Several spans of one operation open at once are counted, because naming
     * it once would understate what is still outstanding.
     */
    public function test_several_open_spans_of_one_operation_are_counted(): void
    {
        $this->profiler->start('render', 'page');
        $this->profiler->start('render', 'page');

        self::assertStringContainsString('render:page (2 open)', $this->runCommand([])->getDisplay());
    }

    /**
     * A consumer that parses gets a field rather than prose.
     */
    public function test_json_carries_the_unfinished_operations_in_a_field(): void
    {
        $this->profiler->start('db-query', 'users');
        $this->profiler->stop('db-query', 'users');
        $this->profiler->start('render', 'page');

        $display = $this->runCommand(['--format' => 'json'])->getDisplay();

        /** @var array{unfinished: array<string, int>} $decoded */
        $decoded = json_decode($display, true);

        self::assertSame(['render:page' => 1], $decoded['unfinished']);
        self::assertStringNotContainsString('Started and never stopped', $display);
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
