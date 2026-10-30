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

    public function test_unresolvable_class_string_reports_the_misconfiguration(): void
    {
        /** @var class-string<ModuleHealthCheckInterface> $bogus */
        $bogus = 'GacelaTest\\NotExisting\\HealthCheck';
        HealthCheckRegistry::register($bogus);
        HealthCheckRegistry::register(HealthCheckRegistryTestFake::class);

        $results = HealthCheckRegistry::createHealthChecker()->checkAll()->getResults();

        self::assertTrue($results['HealthCheck']->isUnhealthy());
        self::assertStringContainsString('class does not exist', $results['HealthCheck']->message);
        self::assertStringContainsString("Run 'composer dump-autoload' to refresh autoloader", $results['HealthCheck']->message);
        self::assertSame(HealthCheckNotResolvableException::class, $results['HealthCheck']->metadata['exception']);
        self::assertTrue($results['Fake']->isHealthy());
    }

    public function test_class_that_is_not_a_health_check_is_never_built(): void
    {
        HealthCheckRegistryNotACheckWithDependency::$built = 0;

        try {
            Gacela::bootstrap(__DIR__);
            /** @var class-string<ModuleHealthCheckInterface> $notACheck */
            $notACheck = HealthCheckRegistryNotACheckWithDependency::class;
            HealthCheckRegistry::register($notACheck);

            $results = HealthCheckRegistry::createHealthChecker(Gacela::container())->checkAll()->getResults();
            $status = $results['HealthCheckRegistryNotACheckWithDependency'];

            self::assertTrue($status->isUnhealthy());
            self::assertStringContainsString('does not implement', $status->message);
            self::assertSame(0, HealthCheckRegistryNotACheckWithDependency::$built);
        } finally {
            Gacela::resetCache();
        }
    }

    public function test_abstract_check_without_a_binding_is_an_unhealthy_result(): void
    {
        HealthCheckRegistry::register(HealthCheckRegistryAbstractFake::class);
        HealthCheckRegistry::register(HealthCheckRegistryInterfaceFake::class);
        HealthCheckRegistry::register(HealthCheckRegistryTestFake::class);

        $results = HealthCheckRegistry::createHealthChecker()->checkAll()->getResults();
        $status = $results['HealthCheckRegistryAbstractFake'];

        self::assertTrue($status->isUnhealthy());
        self::assertSame(HealthCheckNotResolvableException::class, $status->metadata['exception']);
        self::assertStringContainsString('cannot be instantiated', $status->message);
        self::assertTrue($results['HealthCheckRegistryInterfaceFake']->isUnhealthy());
        self::assertStringContainsString('cannot be instantiated', $results['HealthCheckRegistryInterfaceFake']->message);
        self::assertTrue($results['Fake']->isHealthy());
    }

    public function test_abstract_check_without_a_container_binding_reports_the_registry_error(): void
    {
        try {
            Gacela::bootstrap(__DIR__);
            HealthCheckRegistry::register(HealthCheckRegistryAbstractFake::class);

            $status = HealthCheckRegistry::createHealthChecker(Gacela::container())->checkAll()->getResults()['HealthCheckRegistryAbstractFake'];

            self::assertTrue($status->isUnhealthy());
            self::assertSame(HealthCheckNotResolvableException::class, $status->metadata['exception']);
            self::assertStringContainsString('cannot be instantiated', $status->message);
        } finally {
            Gacela::resetCache();
        }
    }

    public function test_abstract_and_interface_checks_keep_aliases_to_autowirable_checks(): void
    {
        try {
            Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
                $config->addAlias(HealthCheckRegistryAbstractFake::class, HealthCheckRegistryConcreteFake::class);
                $config->addAlias(HealthCheckRegistryInterfaceFake::class, HealthCheckRegistryConcreteFake::class);
            });
            HealthCheckRegistry::register(HealthCheckRegistryAbstractFake::class);
            HealthCheckRegistry::register(HealthCheckRegistryInterfaceFake::class);

            $report = HealthCheckRegistry::createHealthChecker(Gacela::container())->checkAll();

            self::assertTrue($report->isHealthy());
            self::assertArrayHasKey('Bound', $report->getResults());
        } finally {
            Gacela::resetCache();
        }
    }

    public function test_abstract_and_interface_checks_keep_working_container_bindings(): void
    {
        try {
            Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
                $config->addFactory(HealthCheckRegistryAbstractFake::class, static fn (): HealthCheckRegistryConcreteFake => new HealthCheckRegistryConcreteFake());
                $config->addFactory(HealthCheckRegistryInterfaceFake::class, static fn (): HealthCheckRegistryConcreteFake => new HealthCheckRegistryConcreteFake());
            });
            HealthCheckRegistry::register(HealthCheckRegistryAbstractFake::class);
            HealthCheckRegistry::register(HealthCheckRegistryInterfaceFake::class);

            $report = HealthCheckRegistry::createHealthChecker(Gacela::container())->checkAll();

            self::assertTrue($report->isHealthy());
            self::assertArrayHasKey('Bound', $report->getResults());
        } finally {
            Gacela::resetCache();
        }
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

    public function test_container_resolution_error_becomes_unhealthy_and_keeps_healthy_peers(): void
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
            HealthCheckRegistry::register(HealthCheckRegistryTestFake::class);

            $results = HealthCheckRegistry::createHealthChecker(Gacela::container())->checkAll()->getResults();
            $status = $results['HealthCheckRegistryConfigurableFake'];

            self::assertTrue($status->isUnhealthy());
            self::assertStringContainsString('boom from factory', $status->message);
            self::assertSame(RuntimeException::class, $status->metadata['exception']);
            self::assertTrue($results['Fake']->isHealthy());
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

final class HealthCheckRegistryNotACheckWithDependency
{
    public static int $built = 0;

    public function __construct(HealthCheckRegistryTestFake $dependency)
    {
        ++self::$built;
    }
}

interface HealthCheckRegistryInterfaceFake extends ModuleHealthCheckInterface
{
}

abstract class HealthCheckRegistryAbstractFake implements HealthCheckRegistryInterfaceFake
{
    public function checkHealth(): HealthStatus
    {
        return HealthStatus::healthy();
    }

    public function getModuleName(): string
    {
        return 'Bound';
    }
}

final class HealthCheckRegistryConcreteFake extends HealthCheckRegistryAbstractFake
{
}
