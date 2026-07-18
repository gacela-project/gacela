<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Health\HealthCheckRegistry;
use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GacelaConfigTest extends TestCase
{
    protected function setUp(): void
    {
        HealthCheckRegistry::reset();
    }

    protected function tearDown(): void
    {
        HealthCheckRegistry::reset();
    }

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

    public function test_app_module_paths_default_to_null_in_transfer(): void
    {
        $config = new GacelaConfig();

        self::assertNull($config->toTransfer()->appModulePaths);
    }

    public function test_get_external_service_reports_numeric_string_key_zero_not_none(): void
    {
        $config = new GacelaConfig();
        $config->addExternalService('0', 'value');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Available keys: 0');

        $config->getExternalService('missing');
    }

    public function test_get_external_service_reports_none_when_no_services_registered(): void
    {
        $config = new GacelaConfig();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Available keys: none');

        $config->getExternalService('missing');
    }

    public function test_set_app_module_paths_exposes_list_on_transfer(): void
    {
        $config = new GacelaConfig();

        $returned = $config->setAppModulePaths(['src/php', '/abs/path']);

        self::assertSame($config, $returned);
        self::assertSame(['src/php', '/abs/path'], $config->toTransfer()->appModulePaths);
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
            HealthCheckRegistry::all(),
        );
    }

    public function test_add_health_check_collects_instance(): void
    {
        $config = new GacelaConfig();
        $instance = new GacelaConfigTestFakeHealthCheck();

        $config->addHealthCheck($instance);

        self::assertSame([$instance], HealthCheckRegistry::all());
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
            HealthCheckRegistry::all(),
        );
    }

    public function test_add_health_check_defaults_to_empty_list(): void
    {
        new GacelaConfig();

        self::assertSame([], HealthCheckRegistry::all());
    }

    public function test_extend_gacela_config_accumulates_across_calls(): void
    {
        $config = (new GacelaConfig())
            ->extendGacelaConfig('FirstConfigToExtend')
            ->extendGacelaConfig('SecondConfigToExtend');

        self::assertSame(
            ['FirstConfigToExtend', 'SecondConfigToExtend'],
            $config->toTransfer()->gacelaConfigsToExtend,
        );
    }

    public function test_add_plugin_accumulates_across_calls(): void
    {
        $pluginA = static function (): void {
        };
        $pluginB = static function (): void {
        };

        $config = (new GacelaConfig())
            ->addPlugin($pluginA)
            ->addPlugin($pluginB);

        self::assertSame([$pluginA, $pluginB], $config->toTransfer()->plugins);
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
