<?php

declare(strict_types=1);

namespace GacelaTest\Integration\MissingFile;

use Gacela\ClassResolver\Config\ConfigNotFoundException;
use Gacela\ClassResolver\DependencyProvider\DependencyProviderNotFoundException;
use Gacela\ClassResolver\Factory\FactoryNotFoundException;
use Gacela\Container\Exception\ContainerKeyNotFoundException;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
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
