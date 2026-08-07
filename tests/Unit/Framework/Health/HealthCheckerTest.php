<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Health;

use Exception;
use Gacela\Framework\Health\HealthChecker;
use Gacela\Framework\Health\HealthLevel;
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
        $check = $this->createStub(ModuleHealthCheckInterface::class);
        $check->method('getModuleName')->willReturn('FailingModule');
        $check->method('checkHealth')->willThrowException(new Exception('Unexpected error'));

        $checker = new HealthChecker([$check]);

        $report = $checker->checkAll();

        self::assertCount(1, $report->getResults());

        $status = $report->getResults()['FailingModule'];
        self::assertTrue($status->isUnhealthy());
        self::assertStringContainsString('Health check failed', $status->message);
        self::assertArrayHasKey('exception', $status->metadata);
        self::assertArrayHasKey('file', $status->metadata);
        self::assertArrayHasKey('line', $status->metadata);
        self::assertSame(Exception::class, $status->metadata['exception']);
        self::assertIsString($status->metadata['file']);
        self::assertIsInt($status->metadata['line']);
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

    public function test_duplicate_module_checks_are_aggregated_to_the_worst_status(): void
    {
        $healthy = $this->createHealthCheck('Orders', HealthStatus::healthy('queue up'));
        $unhealthy = $this->createHealthCheck('Orders', HealthStatus::unhealthy('database down'));

        $report = (new HealthChecker([$healthy, $unhealthy]))->checkAll();
        $status = $report->getResults()['Orders'];

        self::assertCount(1, $report->getResults());
        self::assertTrue($status->isUnhealthy());
        self::assertSame(HealthLevel::UNHEALTHY, $report->getOverallLevel());
        self::assertStringContainsString('[healthy] queue up', $status->message);
        self::assertStringContainsString('[unhealthy] database down', $status->message);
        self::assertCount(2, $status->metadata['health_checks']);
    }

    public function test_a_later_less_severe_check_cannot_overwrite_an_unhealthy_one(): void
    {
        $unhealthy = $this->createHealthCheck('Orders', HealthStatus::unhealthy('database down'));
        $healthy = $this->createHealthCheck('Orders', HealthStatus::healthy('queue up'));
        $degraded = $this->createHealthCheck('Orders', HealthStatus::degraded('queue slow'));

        $followedByHealthy = (new HealthChecker([$unhealthy, $healthy]))->checkAll()->getResults()['Orders'];
        $followedByDegraded = (new HealthChecker([$unhealthy, $degraded]))->checkAll()->getResults()['Orders'];

        self::assertTrue($followedByHealthy->isUnhealthy());
        self::assertTrue($followedByDegraded->isUnhealthy());
    }

    public function test_duplicate_checks_preserve_degraded_as_worse_than_healthy(): void
    {
        $healthy = $this->createHealthCheck('Orders', HealthStatus::healthy('queue up'));
        $degraded = $this->createHealthCheck('Orders', HealthStatus::degraded('queue slow'));

        $first = (new HealthChecker([$healthy, $degraded]))->checkAll()->getResults()['Orders'];
        $last = (new HealthChecker([$degraded, $healthy]))->checkAll()->getResults()['Orders'];

        self::assertTrue($first->isDegraded());
        self::assertTrue($last->isDegraded());
    }

    public function test_a_later_healthy_check_cannot_overwrite_an_exception(): void
    {
        $failing = $this->createStub(ModuleHealthCheckInterface::class);
        $failing->method('getModuleName')->willReturn('Orders');
        $failing->method('checkHealth')->willThrowException(new Exception('connection refused'));
        $healthy = $this->createHealthCheck('Orders', HealthStatus::healthy('queue up'));

        $status = (new HealthChecker([$failing, $healthy]))->checkAll()->getResults()['Orders'];

        self::assertTrue($status->isUnhealthy());
        self::assertStringContainsString('connection refused', $status->message);
        self::assertSame(Exception::class, $status->metadata['health_checks'][0]['metadata']['exception']);
    }

    private function createHealthCheck(string $moduleName, HealthStatus $status): ModuleHealthCheckInterface
    {
        $check = $this->createStub(ModuleHealthCheckInterface::class);
        $check->method('getModuleName')->willReturn($moduleName);
        $check->method('checkHealth')->willReturn($status);

        return $check;
    }
}
