<?php

declare(strict_types=1);

namespace Gacela\Framework\Profiler;

use function count;
use function hrtime;
use function memory_get_usage;
use function round;

/**
 * @psalm-type OperationStats = array{count: int, total_duration: float, avg_duration: float}
 */
final class Profiler
{
    private static ?self $instance = null;

    private bool $enabled = false;

    /** @var list<TProfileEntry> */
    private array $entries = [];

    /**
     * A stack of start times per `operation:subject`, not a single one. The
     * same operation can be in flight more than once -- nested or recursive
     * calls carry identical labels -- and storing one timestamp let the inner
     * start overwrite the outer, so the pair produced a single entry and the
     * outer span was lost.
     *
     * @var array<non-empty-string, non-empty-list<float>>
     */
    private array $activeOperations = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;

        // A span still open when profiling is turned off can never be closed,
        // because stop() does nothing while disabled. Keeping it would let a
        // later enable() pair a fresh stop() with a stale start and record the
        // time the profiler spent switched off.
        $this->activeOperations = [];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param non-empty-string $operation
     * @param non-empty-string $subject
     */
    public function start(string $operation, string $subject): void
    {
        if (!$this->enabled) {
            return;
        }

        $key = $operation . ':' . $subject;
        $this->activeOperations[$key][] = $this->getCurrentTime();
    }

    /**
     * @param non-empty-string $operation
     * @param non-empty-string $subject
     */
    public function stop(string $operation, string $subject): void
    {
        if (!$this->enabled) {
            return;
        }

        $endTime = $this->getCurrentTime();
        $key = $operation . ':' . $subject;

        if (!isset($this->activeOperations[$key])) {
            // A stop() with no matching start() is ignored rather than
            // recorded: there is no start time to measure from.
            return;
        }

        // Innermost first, so a stop closes the span the matching start opened.
        $stack = $this->activeOperations[$key];
        $startTime = array_pop($stack);

        // The key is dropped rather than left holding an empty stack, so
        // `isset()` above stays the whole "is anything in flight" question.
        if ($stack === []) {
            unset($this->activeOperations[$key]);
        } else {
            $this->activeOperations[$key] = $stack;
        }

        $duration = $endTime - $startTime;

        $this->entries[] = new TProfileEntry(
            operation: $operation,
            subject: $subject,
            startTime: $startTime,
            endTime: $endTime,
            duration: $duration,
            memoryUsage: memory_get_usage(true),
        );
    }

    /**
     * @return list<TProfileEntry>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * @return array{
     *     total_operations: int,
     *     total_duration: float,
     *     avg_duration: float,
     *     peak_memory: int,
     *     by_operation: array<string, OperationStats>
     * }
     */
    public function getStats(): array
    {
        $totalDuration = 0.0;
        $peakMemory = 0;

        /** @var array<string, array{count: int, total_duration: float}> $tally */
        $tally = [];
        foreach ($this->entries as $entry) {
            $totalDuration += $entry->duration;
            $peakMemory = max($peakMemory, $entry->memoryUsage);

            if (!isset($tally[$entry->operation])) {
                $tally[$entry->operation] = ['count' => 0, 'total_duration' => 0.0];
            }

            ++$tally[$entry->operation]['count'];
            $tally[$entry->operation]['total_duration'] += $entry->duration;
        }

        /** @var array<string, OperationStats> $byOperation */
        $byOperation = [];
        foreach ($tally as $operation => $stats) {
            $byOperation[$operation] = [
                'count' => $stats['count'],
                'total_duration' => $stats['total_duration'],
                'avg_duration' => $this->average($stats['total_duration'], $stats['count']),
            ];
        }

        $totalOperations = count($this->entries);

        return [
            'total_operations' => $totalOperations,
            'total_duration' => round($totalDuration, 6),
            'avg_duration' => $this->average($totalDuration, $totalOperations),
            'peak_memory' => $peakMemory,
            'by_operation' => $byOperation,
        ];
    }

    public function reset(): void
    {
        $this->entries = [];
        $this->activeOperations = [];
    }

    /**
     * @infection-ignore-all
     */
    private function average(float $sum, int $count): float
    {
        if ($count === 0) {
            return 0.0;
        }

        return round($sum / (float)$count, 6);
    }

    private function getCurrentTime(): float
    {
        return (float)hrtime(true) / 1e9;
    }
}
