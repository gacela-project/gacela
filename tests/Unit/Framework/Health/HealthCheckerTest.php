<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Health;

use Exception;
use Gacela\Framework\Health\HealthChecker;
use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use PHPUnit\Framework\TestCase;

final class HealthCheckerTest extends TestCase
{
    public function test_check_all_returns_empty_report_with_no_checks(): void
    {
        $checker = new HealthChecker([]);

        $report = $checker->checkAll();

        self::assertSame([], $report->getResults());
        self::assertTrue($report->isHealthy());
    }

    public function test_check_all_runs_all_health_checks(): void
    {
        $check1 = $this->createHealthCheck('Module1', HealthStatus::healthy());
        $check2 = $this->createHealthCheck('Module2', HealthStatus::healthy());

        $checker = new HealthChecker([$check1, $check2]);

        $report = $checker->checkAll();

        self::assertCount(2, $report->getResults());
        self::assertTrue($report->isHealthy());
    }

    public function test_check_all_handles_failing_health_checks(): void
    {
        $check1 = $this->createHealthCheck('Module1', HealthStatus::healthy());
        $check2 = $this->createHealthCheck('Module2', HealthStatus::unhealthy('Service down'));

        $checker = new HealthChecker([$check1, $check2]);

        $report = $checker->checkAll();

        self::assertCount(2, $report->getResults());
        self::assertFalse($report->isHealthy());
        self::assertTrue($report->hasUnhealthyModules());
    }

    public function test_check_all_catches_exceptions(): void
    {
        $check = $this->createMock(ModuleHealthCheckInterface::class);
        $check->method('getModuleName')->willReturn('FailingModule');
        $check->method('checkHealth')->willThrowException(new Exception('Unexpected error'));

        $checker = new HealthChecker([$check]);

        $report = $checker->checkAll();

        self::assertCount(1, $report->getResults());

        $status = $report->getResults()['FailingModule'];
        self::assertTrue($status->isUnhealthy());
        self::assertStringContainsString('Health check failed', $status->message);
        self::assertArrayHasKey('exception', $status->metadata);
    }

    public function test_count_returns_number_of_checks(): void
    {
        $check1 = $this->createHealthCheck('Module1', HealthStatus::healthy());
        $check2 = $this->createHealthCheck('Module2', HealthStatus::healthy());
        $check3 = $this->createHealthCheck('Module3', HealthStatus::healthy());

        $checker = new HealthChecker([$check1, $check2, $check3]);

        self::assertSame(3, $checker->count());
    }

    public function test_check_all_preserves_module_names(): void
    {
        $check1 = $this->createHealthCheck('UserModule', HealthStatus::healthy());
        $check2 = $this->createHealthCheck('OrderModule', HealthStatus::degraded('Slow'));

        $checker = new HealthChecker([$check1, $check2]);

        $report = $checker->checkAll();
        $results = $report->getResults();

        self::assertArrayHasKey('UserModule', $results);
        self::assertArrayHasKey('OrderModule', $results);
    }

    private function createHealthCheck(string $moduleName, HealthStatus $status): ModuleHealthCheckInterface
    {
        $check = $this->createMock(ModuleHealthCheckInterface::class);
        $check->method('getModuleName')->willReturn($moduleName);
        $check->method('checkHealth')->willReturn($status);

        return $check;
    }
}
