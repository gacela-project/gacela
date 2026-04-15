<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Health;

use Gacela\Framework\Health\HealthCheckRegistry;
use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use PHPUnit\Framework\TestCase;

final class HealthCheckRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        HealthCheckRegistry::reset();
    }

    protected function tearDown(): void
    {
        HealthCheckRegistry::reset();
    }

    public function test_reset_clears_all_registered_checks(): void
    {
        HealthCheckRegistry::register(HealthCheckRegistryTestFake::class);

        HealthCheckRegistry::reset();

        self::assertSame([], HealthCheckRegistry::all());
    }

    public function test_all_returns_registered_checks_in_order(): void
    {
        $instance = new HealthCheckRegistryTestFake();

        HealthCheckRegistry::register(HealthCheckRegistryTestFake::class);
        HealthCheckRegistry::register($instance);

        self::assertSame(
            [HealthCheckRegistryTestFake::class, $instance],
            HealthCheckRegistry::all(),
        );
    }

    public function test_create_health_checker_resolves_class_strings_into_instances(): void
    {
        HealthCheckRegistry::register(HealthCheckRegistryTestFake::class);

        $checker = HealthCheckRegistry::createHealthChecker();
        $report = $checker->checkAll();

        self::assertArrayHasKey('Fake', $report->getResults());
        self::assertTrue($report->isHealthy());
    }

    public function test_create_health_checker_passes_instances_through(): void
    {
        $instance = new HealthCheckRegistryTestFake();
        HealthCheckRegistry::register($instance);

        $checker = HealthCheckRegistry::createHealthChecker();
        $report = $checker->checkAll();

        self::assertArrayHasKey('Fake', $report->getResults());
    }

    public function test_create_health_checker_returns_empty_when_nothing_registered(): void
    {
        $checker = HealthCheckRegistry::createHealthChecker();

        self::assertSame(0, $checker->count());
    }
}

final class HealthCheckRegistryTestFake implements ModuleHealthCheckInterface
{
    public function checkHealth(): HealthStatus
    {
        return HealthStatus::healthy();
    }

    public function getModuleName(): string
    {
        return 'Fake';
    }
}
