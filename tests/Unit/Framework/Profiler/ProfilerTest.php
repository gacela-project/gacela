<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Profiler;

use Gacela\Framework\Profiler\Profiler;
use Gacela\Framework\Profiler\TProfileEntry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_sum;
use function round;

final class ProfilerTest extends TestCase
{
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

    public function test_profiler_is_singleton(): void
    {
        $instance1 = Profiler::getInstance();
        $instance2 = Profiler::getInstance();

        self::assertSame($instance1, $instance2);
    }

    public function test_profiler_can_be_enabled_and_disabled(): void
    {
        $this->profiler->disable();
        self::assertFalse($this->profiler->isEnabled());

        $this->profiler->enable();
        self::assertTrue($this->profiler->isEnabled());
    }

    public function test_profiler_tracks_operations(): void
    {
        $this->profiler->start('test_operation', 'test_subject');
        usleep(1000); // Sleep for 1ms
        $this->profiler->stop('test_operation', 'test_subject');

        $entries = $this->profiler->getEntries();

        self::assertCount(1, $entries);
        self::assertSame('test_operation', $entries[0]->operation);
        self::assertSame('test_subject', $entries[0]->subject);
        self::assertGreaterThan(0, $entries[0]->duration);
    }

    public function test_profiler_tracks_multiple_operations(): void
    {
        $this->profiler->start('operation1', 'subject1');
        $this->profiler->stop('operation1', 'subject1');

        $this->profiler->start('operation2', 'subject2');
        $this->profiler->stop('operation2', 'subject2');

        $entries = $this->profiler->getEntries();

        self::assertCount(2, $entries);
    }

    public function test_profiler_generates_statistics(): void
    {
        $this->profiler->start('operation1', 'subject1');
        usleep(1000); // Add small delay to ensure measurable duration
        $this->profiler->stop('operation1', 'subject1');

        $this->profiler->start('operation1', 'subject2');
        usleep(1000); // Add small delay to ensure measurable duration
        $this->profiler->stop('operation1', 'subject2');

        $stats = $this->profiler->getStats();

        self::assertSame(2, $stats['total_operations']);
        self::assertGreaterThan(0, $stats['total_duration']);
        self::assertGreaterThan(0, $stats['avg_duration']);
        self::assertGreaterThan(0, $stats['peak_memory']);
        self::assertArrayHasKey('operation1', $stats['by_operation']);
        self::assertSame(2, $stats['by_operation']['operation1']['count']);
    }

    public function test_profiler_can_be_reset(): void
    {
        $this->profiler->start('operation1', 'subject1');
        $this->profiler->stop('operation1', 'subject1');

        self::assertCount(1, $this->profiler->getEntries());

        $this->profiler->reset();

        self::assertCount(0, $this->profiler->getEntries());
    }

    public function test_profiler_does_not_track_when_disabled(): void
    {
        $this->profiler->disable();

        $this->profiler->start('operation1', 'subject1');
        $this->profiler->stop('operation1', 'subject1');

        self::assertCount(0, $this->profiler->getEntries());
    }

    public function test_profiler_handles_missing_stop(): void
    {
        $this->profiler->start('operation1', 'subject1');
        // Intentionally not stopping

        $this->profiler->stop('operation2', 'subject2'); // Stop different operation

        self::assertCount(0, $this->profiler->getEntries());
    }

    public function test_get_stats_returns_zero_values_when_no_entries_are_recorded(): void
    {
        $stats = $this->profiler->getStats();

        self::assertSame(0, $stats['total_operations']);
        self::assertSame(0.0, $stats['total_duration']);
        self::assertSame(0.0, $stats['avg_duration']);
        self::assertSame(0, $stats['peak_memory']);
        self::assertSame([], $stats['by_operation']);
    }

    public function test_duration_equals_difference_between_end_and_start_time(): void
    {
        $this->profiler->start('op', 'subj');
        usleep(1000);
        $this->profiler->stop('op', 'subj');

        $entry = $this->profiler->getEntries()[0];

        self::assertSame($entry->endTime - $entry->startTime, $entry->duration);
    }

    public function test_current_time_is_in_seconds_so_measurable_durations_are_sub_second(): void
    {
        // Guards against mutating `hrtime(true) / 1e9` → keeps the value in the
        // second-scale range for short operations. A wrong divisor would blow
        // the duration up by many orders of magnitude.
        $this->profiler->start('op', 'subj');
        usleep(1000);
        $this->profiler->stop('op', 'subj');

        $entry = $this->profiler->getEntries()[0];

        self::assertLessThan(1.0, $entry->duration);
        self::assertGreaterThan(0.0, $entry->duration);
    }

    public function test_total_duration_is_the_sum_of_all_entry_durations(): void
    {
        $this->injectEntries([
            $this->entry('op1', 's1', duration: 0.001),
            $this->entry('op1', 's2', duration: 0.002),
            $this->entry('op2', 's3', duration: 0.004),
        ]);

        $stats = $this->profiler->getStats();

        self::assertSame(round(0.001 + 0.002 + 0.004, 6), $stats['total_duration']);
    }

    public function test_avg_duration_is_total_divided_by_count_rounded_to_six_decimals(): void
    {
        $this->injectEntries([
            $this->entry('op', 's1', duration: 0.001),
            $this->entry('op', 's2', duration: 0.002),
            $this->entry('op', 's3', duration: 0.0035),
        ]);

        $stats = $this->profiler->getStats();

        $expected = round(array_sum([0.001, 0.002, 0.0035]) / 3.0, 6);
        self::assertSame($expected, $stats['avg_duration']);
    }

    public function test_per_operation_total_duration_is_sum_of_that_operations_entries(): void
    {
        $this->injectEntries([
            $this->entry('op1', 's1', duration: 0.001),
            $this->entry('op1', 's2', duration: 0.002),
            $this->entry('op2', 's3', duration: 0.004),
        ]);

        $stats = $this->profiler->getStats();

        self::assertSame(0.001 + 0.002, $stats['by_operation']['op1']['total_duration']);
        self::assertSame(0.004, $stats['by_operation']['op2']['total_duration']);
    }

    public function test_per_operation_avg_duration_is_that_operations_total_over_count(): void
    {
        $this->injectEntries([
            $this->entry('op', 's1', duration: 0.001),
            $this->entry('op', 's2', duration: 0.002),
            $this->entry('op', 's3', duration: 0.003),
        ]);

        $stats = $this->profiler->getStats();

        self::assertSame(round((0.001 + 0.002 + 0.003) / 3.0, 6), $stats['by_operation']['op']['avg_duration']);
    }

    public function test_peak_memory_is_the_max_memory_usage_across_entries(): void
    {
        $this->injectEntries([
            $this->entry('op', 's1', memoryUsage: 1024),
            $this->entry('op', 's2', memoryUsage: 4096),
            $this->entry('op', 's3', memoryUsage: 2048),
        ]);

        $stats = $this->profiler->getStats();

        self::assertSame(4096, $stats['peak_memory']);
    }

    public function test_get_stats_rounds_total_and_avg_to_six_decimal_places(): void
    {
        // Picks durations whose mean has a non-zero 7th decimal digit so that
        // mutations flipping `round()` to `ceil()`/`floor()` or dropping the
        // precision argument produce a visibly different value.
        $this->injectEntries([
            $this->entry('op', 's1', duration: 0.1234567),
            $this->entry('op', 's2', duration: 0.7654321),
        ]);

        $stats = $this->profiler->getStats();

        self::assertSame(round(0.1234567 + 0.7654321, 6), $stats['total_duration']);
        self::assertSame(round((0.1234567 + 0.7654321) / 2.0, 6), $stats['avg_duration']);
        self::assertSame(round((0.1234567 + 0.7654321) / 2.0, 6), $stats['by_operation']['op']['avg_duration']);
    }

    /**
     * @param list<TProfileEntry> $entries
     */
    private function injectEntries(array $entries): void
    {
        $reflection = new ReflectionClass(Profiler::class);
        $property = $reflection->getProperty('entries');
        $property->setValue($this->profiler, $entries);
    }

    private function entry(
        string $operation,
        string $subject,
        float $duration = 0.001,
        int $memoryUsage = 0,
        float $startTime = 0.0,
    ): TProfileEntry {
        return new TProfileEntry(
            operation: $operation,
            subject: $subject,
            startTime: $startTime,
            endTime: $startTime + $duration,
            duration: $duration,
            memoryUsage: $memoryUsage,
        );
    }
}
