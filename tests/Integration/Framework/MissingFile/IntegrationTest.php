<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\MissingFile;

use Gacela\Framework\ClassResolver\Config\ConfigNotFoundException;
use Gacela\Framework\ClassResolver\DependencyProvider\DependencyProviderNotFoundException;
use Gacela\Framework\ClassResolver\Factory\FactoryNotFoundException;
use Gacela\Framework\Container\Exception\ContainerKeyNotFoundException;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_missing_factory_module(): void
    {
        $this->expectException(FactoryNotFoundException::class);

        $facade = new MissingFactoryFile\Facade();
        $facade->error();
    }

    public function test_missing_config_module(): void
    {
        $this->expectException(ConfigNotFoundException::class);

        $facade = new MissingConfigFile\Facade();
        $facade->error();
    }

    public function test_missing_dependency_provider_module(): void
    {
        $this->expectException(DependencyProviderNotFoundException::class);

        $facade = new MissingDependencyProviderFile\Facade();
        $facade->error();
    }

    public function test_missing_container_service_key_module(): void
    {
        $this->expectException(ContainerKeyNotFoundException::class);

        $facade = new MissingContainerServiceKey\Facade();
        $facade->error();
    }
}
