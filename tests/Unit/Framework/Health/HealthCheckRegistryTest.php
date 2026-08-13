<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Health;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Gacela\Framework\Health\HealthCheckNotResolvableException;
use Gacela\Framework\Health\HealthCheckRegistry;
use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use function sprintf;

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

    /**
     * A registered check that cannot be resolved is a misconfiguration, not a
     * reason to report a healthy system: skipping it silently would hide the
     * very problem health checks exist to surface.
     */
    public function test_unresolvable_class_string_reports_the_misconfiguration(): void
    {
        /** @var class-string<ModuleHealthCheckInterface> $bogus */
        $bogus = 'GacelaTest\NotExisting\HealthCheck';
        HealthCheckRegistry::register($bogus);

        $this->expectException(HealthCheckNotResolvableException::class);
        $this->expectExceptionMessage(
            'The health check "GacelaTest\NotExisting\HealthCheck" was registered but the class does not exist. '
            . 'Check the class-string passed to GacelaConfig::addHealthCheck().',
        );

        HealthCheckRegistry::createHealthChecker();
    }

    /**
     * The message names the call to check; the tips name the two things that
     * usually make a registered class-string resolve to nothing.
     */
    public function test_an_unresolvable_class_string_carries_the_tips_for_a_missing_class(): void
    {
        /** @var class-string<ModuleHealthCheckInterface> $bogus */
        $bogus = 'GacelaTest\\NotExisting\\HealthCheck';
        HealthCheckRegistry::register($bogus);

        try {
            HealthCheckRegistry::createHealthChecker();
            self::fail('Expected HealthCheckNotResolvableException');
        } catch (HealthCheckNotResolvableException $healthCheckNotResolvableException) {
            $message = $healthCheckNotResolvableException->getMessage();

            self::assertStringContainsString("Run 'composer dump-autoload' to refresh autoloader", $message);
            // What went wrong first, what to try about it after.
            self::assertStringStartsWith('The health check "', $message);
        }
    }

    public function test_class_that_is_not_a_health_check_reports_the_misconfiguration(): void
    {
        /** @var class-string<ModuleHealthCheckInterface> $notACheck */
        $notACheck = HealthCheckRegistryNotACheck::class;
        HealthCheckRegistry::register($notACheck);

        $this->expectException(HealthCheckNotResolvableException::class);
        $this->expectExceptionMessage(sprintf(
            'The health check "%s" does not implement %s.',
            HealthCheckRegistryNotACheck::class,
            ModuleHealthCheckInterface::class,
        ));

        HealthCheckRegistry::createHealthChecker();
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

            $report = HealthCheckRegistry::createHealthChecker(Gacela::container())->checkAll();

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

            $report = HealthCheckRegistry::createHealthChecker(Gacela::container())->checkAll();

            self::assertArrayHasKey('default', $report->getResults());
        } finally {
            Gacela::resetCache();
        }
    }

    public function test_container_resolution_error_propagates_instead_of_being_swallowed(): void
    {
        try {
            Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
                $config->addFactory(
                    HealthCheckRegistryConfigurableFake::class,
                    static function (): HealthCheckRegistryConfigurableFake {
                        throw new RuntimeException('boom from factory');
                    },
                );
            });
            HealthCheckRegistry::register(HealthCheckRegistryConfigurableFake::class);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('boom from factory');

            HealthCheckRegistry::createHealthChecker(Gacela::container());
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

final class HealthCheckRegistryNotACheck
{
}
