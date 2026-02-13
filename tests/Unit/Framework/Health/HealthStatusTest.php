<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Health;

use Gacela\Framework\Health\HealthLevel;
use Gacela\Framework\Health\HealthStatus;
use PHPUnit\Framework\TestCase;

final class HealthStatusTest extends TestCase
{
    public function test_healthy_status(): void
    {
        $status = HealthStatus::healthy('All systems operational');

        self::assertTrue($status->isHealthy());
        self::assertFalse($status->isDegraded());
        self::assertFalse($status->isUnhealthy());
        self::assertSame(HealthLevel::HEALTHY, $status->level);
        self::assertSame('All systems operational', $status->message);
    }

    public function test_degraded_status(): void
    {
        $status = HealthStatus::degraded('Slow response times', ['latency' => '500ms']);

        self::assertFalse($status->isHealthy());
        self::assertTrue($status->isDegraded());
        self::assertFalse($status->isUnhealthy());
        self::assertSame(HealthLevel::DEGRADED, $status->level);
        self::assertSame('Slow response times', $status->message);
        self::assertSame(['latency' => '500ms'], $status->metadata);
    }

    public function test_unhealthy_status(): void
    {
        $status = HealthStatus::unhealthy('Database connection failed');

        self::assertFalse($status->isHealthy());
        self::assertFalse($status->isDegraded());
        self::assertTrue($status->isUnhealthy());
        self::assertSame(HealthLevel::UNHEALTHY, $status->level);
        self::assertSame('Database connection failed', $status->message);
    }

    public function test_to_array_includes_all_data(): void
    {
        $status = HealthStatus::degraded('Issue detected', ['detail' => 'value']);

        $array = $status->toArray();

        self::assertSame([
            'level' => 'degraded',
            'message' => 'Issue detected',
            'metadata' => ['detail' => 'value'],
        ], $array);
    }

    public function test_healthy_status_with_default_message(): void
    {
        $status = HealthStatus::healthy();

        self::assertSame('Module is healthy', $status->message);
        self::assertSame([], $status->metadata);
    }

    public function test_metadata_is_accessible(): void
    {
        $metadata = [
            'database' => 'connected',
            'cache' => 'operational',
            'storage' => 'available',
        ];

        $status = HealthStatus::healthy('All good', $metadata);

        self::assertSame($metadata, $status->metadata);
    }
}
