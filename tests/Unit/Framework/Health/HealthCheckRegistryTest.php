<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Health;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Gacela\Framework\Health\HealthCheckRegistry;
use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use PHPUnit\Framework\TestCase;
use stdClass;

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

    public function test_resolves_class_strings_registered_after_instances(): void
    {
        HealthCheckRegistry::register(new HealthCheckRegistryTestFake());
        HealthCheckRegistry::register(HealthCheckRegistrySecondFake::class);

        $report = HealthCheckRegistry::createHealthChecker()->checkAll();

        self::assertArrayHasKey('Fake', $report->getResults());
        self::assertArrayHasKey('SecondFake', $report->getResults());
    }

    public function test_unresolvable_class_string_is_skipped(): void
    {
        /** @var class-string<ModuleHealthCheckInterface> $bogus */
        $bogus = 'GacelaTest\NotExisting\HealthCheck';
        HealthCheckRegistry::register($bogus);

        $checker = HealthCheckRegistry::createHealthChecker();

        self::assertSame(0, $checker->count());
    }

    public function test_container_provided_instance_is_preferred_over_direct_instantiation(): void
    {
        try {
            Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
                $config->addFactory(
                    HealthCheckRegistryConfigurableFake::class,
                    static fn (): HealthCheckRegistryConfigurableFake => new HealthCheckRegistryConfigurableFake('from-container'),
                );
            });
            HealthCheckRegistry::register(HealthCheckRegistryConfigurableFake::class);

            $report = HealthCheckRegistry::createHealthChecker()->checkAll();

            self::assertArrayHasKey('from-container', $report->getResults());
        } finally {
            Gacela::resetCache();
        }
    }

    public function test_container_returning_non_check_falls_back_to_direct_instantiation(): void
    {
        try {
            Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
                $config->addFactory(
                    HealthCheckRegistryConfigurableFake::class,
                    static fn (): stdClass => new stdClass(),
                );
            });
            HealthCheckRegistry::register(HealthCheckRegistryConfigurableFake::class);

            $report = HealthCheckRegistry::createHealthChecker()->checkAll();

            self::assertArrayHasKey('default', $report->getResults());
        } finally {
            Gacela::resetCache();
        }
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

final class HealthCheckRegistrySecondFake implements ModuleHealthCheckInterface
{
    public function checkHealth(): HealthStatus
    {
        return HealthStatus::healthy();
    }

    public function getModuleName(): string
    {
        return 'SecondFake';
    }
}

final class HealthCheckRegistryConfigurableFake implements ModuleHealthCheckInterface
{
    public function __construct(
        private readonly string $name = 'default',
    ) {
    }

    public function checkHealth(): HealthStatus
    {
        return HealthStatus::healthy();
    }

    public function getModuleName(): string
    {
        return $this->name;
    }
}
