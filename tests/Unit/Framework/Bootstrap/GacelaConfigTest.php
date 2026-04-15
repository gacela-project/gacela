<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use PHPUnit\Framework\TestCase;

final class GacelaConfigTest extends TestCase
{
    public function test_default_php_config_registers_default_paths(): void
    {
        $closure = GacelaConfig::defaultPhpConfig();
        $config = new GacelaConfig();

        $closure($config);

        $dto = $config->toTransfer();

        $paths = $dto->appConfigBuilder->build();
        self::assertCount(1, $paths);
        self::assertSame('config/*.php', $paths[0]->path());
        self::assertSame('config/local.php', $paths[0]->pathLocal());
    }

    public function test_add_mapping_interface_is_an_alias_of_add_binding(): void
    {
        $a = new GacelaConfig();
        $b = new GacelaConfig();

        $a->addMappingInterface('App\\Port', 'App\\Adapter');
        $b->addBinding('App\\Port', 'App\\Adapter');

        self::assertEquals(
            $a->toTransfer()->bindingsBuilder->build(),
            $b->toTransfer()->bindingsBuilder->build(),
        );
    }

    public function test_add_health_check_collects_class_string(): void
    {
        $config = new GacelaConfig();

        $config->addHealthCheck(GacelaConfigTestFakeHealthCheck::class);

        self::assertSame(
            [GacelaConfigTestFakeHealthCheck::class],
            $config->toTransfer()->healthChecks,
        );
    }

    public function test_add_health_check_collects_instance(): void
    {
        $config = new GacelaConfig();
        $instance = new GacelaConfigTestFakeHealthCheck();

        $config->addHealthCheck($instance);

        self::assertSame([$instance], $config->toTransfer()->healthChecks);
    }

    public function test_add_health_check_is_fluent(): void
    {
        $config = new GacelaConfig();

        $result = $config->addHealthCheck(GacelaConfigTestFakeHealthCheck::class);

        self::assertSame($config, $result);
    }

    public function test_add_health_check_preserves_registration_order(): void
    {
        $config = new GacelaConfig();
        $instance = new GacelaConfigTestFakeHealthCheck();

        $config
            ->addHealthCheck(GacelaConfigTestFakeHealthCheck::class)
            ->addHealthCheck($instance);

        self::assertSame(
            [GacelaConfigTestFakeHealthCheck::class, $instance],
            $config->toTransfer()->healthChecks,
        );
    }

    public function test_add_health_check_defaults_to_empty_list(): void
    {
        $config = new GacelaConfig();

        self::assertSame([], $config->toTransfer()->healthChecks);
    }
}

final class GacelaConfigTestFakeHealthCheck implements ModuleHealthCheckInterface
{
    public function checkHealth(): HealthStatus
    {
        return HealthStatus::healthy();
    }

    public function getModuleName(): string
    {
        return 'GacelaConfigTestFake';
    }
}
