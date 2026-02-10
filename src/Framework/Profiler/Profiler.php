<?php

declare(strict_types=1);

namespace Gacela\Framework\Profiler;

use function count;
use function hrtime;
use function memory_get_usage;
use function round;

final class Profiler
{
    private static ?self $instance = null;

    private bool $enabled = false;

    /** @var list<TProfileEntry> */
    private array $entries = [];

    /** @var array<non-empty-string, float> */
    private array $activeOperations = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
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
        $this->activeOperations[$key] = $this->getCurrentTime();
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
            return;
        }

        $startTime = $this->activeOperations[$key];
        $duration = $endTime - $startTime;

        $this->entries[] = new TProfileEntry(
            operation: $operation,
            subject: $subject,
            startTime: $startTime,
            endTime: $endTime,
            duration: $duration,
            memoryUsage: memory_get_usage(true),
        );

        unset($this->activeOperations[$key]);
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
     *     by_operation: array<string, array{count: int, total_duration: float, avg_duration: float}>
     * }
     */
    public function getStats(): array
    {
        $totalDuration = 0.0;
        $peakMemory = 0;
        $byOperation = [];

        foreach ($this->entries as $entry) {
            $totalDuration += $entry->duration;
            $peakMemory = max($peakMemory, $entry->memoryUsage);

            if (!isset($byOperation[$entry->operation])) {
                $byOperation[$entry->operation] = [
                    'count' => 0,
                    'total_duration' => 0.0,
                    'avg_duration' => 0.0,
                ];
            }

            ++$byOperation[$entry->operation]['count'];
            $byOperation[$entry->operation]['total_duration'] += $entry->duration;
        }

        foreach ($byOperation as $operation => $stats) {
            $byOperation[$operation]['avg_duration'] = $stats['count'] > 0
                ? round($stats['total_duration'] / (float) $stats['count'], 6)
                : 0.0;
        }

        return [
            'total_operations' => count($this->entries),
            'total_duration' => round($totalDuration, 6),
            'avg_duration' => count($this->entries) > 0
                ? round($totalDuration / (float) count($this->entries), 6)
                : 0.0,
            'peak_memory' => $peakMemory,
            'by_operation' => $byOperation,
        ];
    }

    public function reset(): void
    {
        $this->entries = [];
        $this->activeOperations = [];
    }

    private function getCurrentTime(): float
    {
        return hrtime(true) / 1e9; // Convert nanoseconds to seconds
    }
}
