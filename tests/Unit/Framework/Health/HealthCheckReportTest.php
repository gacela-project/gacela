<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Health;

use Gacela\Framework\Health\HealthCheckReport;
use Gacela\Framework\Health\HealthLevel;
use Gacela\Framework\Health\HealthStatus;
use PHPUnit\Framework\TestCase;

final class HealthCheckReportTest extends TestCase
{
    public function test_is_healthy_returns_true_when_all_modules_healthy(): void
    {
        $report = new HealthCheckReport([
            'Module1' => HealthStatus::healthy(),
            'Module2' => HealthStatus::healthy(),
        ]);

        self::assertTrue($report->isHealthy());
    }

    public function test_is_healthy_returns_false_when_any_module_degraded(): void
    {
        $report = new HealthCheckReport([
            'Module1' => HealthStatus::healthy(),
            'Module2' => HealthStatus::degraded('Issue'),
        ]);

        self::assertFalse($report->isHealthy());
    }

    public function test_has_unhealthy_modules_returns_true_when_any_unhealthy(): void
    {
        $report = new HealthCheckReport([
            'Module1' => HealthStatus::healthy(),
            'Module2' => HealthStatus::unhealthy('Down'),
        ]);

        self::assertTrue($report->hasUnhealthyModules());
    }

    public function test_has_unhealthy_modules_returns_false_when_none_unhealthy(): void
    {
        $report = new HealthCheckReport([
            'Module1' => HealthStatus::healthy(),
            'Module2' => HealthStatus::degraded('Slow'),
        ]);

        self::assertFalse($report->hasUnhealthyModules());
    }

    public function test_get_results_returns_all_results(): void
    {
        $status1 = HealthStatus::healthy();
        $status2 = HealthStatus::degraded('Issue');

        $report = new HealthCheckReport([
            'Module1' => $status1,
            'Module2' => $status2,
        ]);

        $results = $report->getResults();

        self::assertCount(2, $results);
        self::assertSame($status1, $results['Module1']);
        self::assertSame($status2, $results['Module2']);
    }

    public function test_get_results_by_level_filters_correctly(): void
    {
        $report = new HealthCheckReport([
            'Module1' => HealthStatus::healthy(),
            'Module2' => HealthStatus::degraded('Issue1'),
            'Module3' => HealthStatus::unhealthy('Down'),
            'Module4' => HealthStatus::degraded('Issue2'),
        ]);

        $degraded = $report->getResultsByLevel(HealthLevel::DEGRADED);

        self::assertCount(2, $degraded);
        self::assertArrayHasKey('Module2', $degraded);
        self::assertArrayHasKey('Module4', $degraded);
    }

    public function test_get_overall_level_returns_unhealthy_when_any_unhealthy(): void
    {
        $report = new HealthCheckReport([
            'Module1' => HealthStatus::healthy(),
            'Module2' => HealthStatus::degraded('Issue'),
            'Module3' => HealthStatus::unhealthy('Down'),
        ]);

        self::assertSame(HealthLevel::UNHEALTHY, $report->getOverallLevel());
    }

    public function test_get_overall_level_returns_degraded_when_any_degraded(): void
    {
        $report = new HealthCheckReport([
            'Module1' => HealthStatus::healthy(),
            'Module2' => HealthStatus::degraded('Issue'),
        ]);

        self::assertSame(HealthLevel::DEGRADED, $report->getOverallLevel());
    }

    public function test_get_overall_level_returns_healthy_when_all_healthy(): void
    {
        $report = new HealthCheckReport([
            'Module1' => HealthStatus::healthy(),
            'Module2' => HealthStatus::healthy(),
        ]);

        self::assertSame(HealthLevel::HEALTHY, $report->getOverallLevel());
    }

    public function test_to_array_formats_correctly(): void
    {
        $report = new HealthCheckReport([
            'Module1' => HealthStatus::healthy('OK'),
            'Module2' => HealthStatus::degraded('Slow', ['latency' => '200ms']),
        ]);

        $array = $report->toArray();

        self::assertSame('degraded', $array['overall']);
        self::assertArrayHasKey('modules', $array);
        self::assertArrayHasKey('Module1', $array['modules']);
        self::assertArrayHasKey('Module2', $array['modules']);
        self::assertSame('healthy', $array['modules']['Module1']['level']);
        self::assertSame('degraded', $array['modules']['Module2']['level']);
    }

    public function test_empty_report_is_healthy(): void
    {
        $report = new HealthCheckReport([]);

        self::assertTrue($report->isHealthy());
        self::assertFalse($report->hasUnhealthyModules());
        self::assertSame(HealthLevel::HEALTHY, $report->getOverallLevel());
    }
}
