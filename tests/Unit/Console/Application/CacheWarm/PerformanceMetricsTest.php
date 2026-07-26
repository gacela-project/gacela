<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\CacheWarm;

use Gacela\Console\Application\CacheWarm\PerformanceMetrics;
use PHPUnit\Framework\TestCase;

use function memory_get_usage;

final class PerformanceMetricsTest extends TestCase
{
    public function test_elapsed_time_is_measured_from_construction_not_from_the_epoch(): void
    {
        $metrics = new PerformanceMetrics();

        $elapsed = $metrics->getElapsedTime();

        self::assertGreaterThanOrEqual(0.0, $elapsed);
        self::assertLessThan(60.0, $elapsed, 'the elapsed time must be a delta, not a unix timestamp');
    }

    public function test_memory_used_is_a_delta_not_the_total_process_usage(): void
    {
        $metrics = new PerformanceMetrics();

        self::assertLessThan(memory_get_usage(true), $metrics->getMemoryUsed());
    }

    public function test_elapsed_time_is_formatted_with_three_decimals(): void
    {
        $metrics = new PerformanceMetrics();

        self::assertMatchesRegularExpression('/^\d+\.\d{3} seconds$/', $metrics->formatElapsedTime());
    }

    public function test_memory_used_is_formatted_as_human_readable_bytes(): void
    {
        $metrics = new PerformanceMetrics();

        self::assertMatchesRegularExpression('/^\d+(\.\d{2})? (B|KB|MB)$/', $metrics->formatMemoryUsed());
    }
}
