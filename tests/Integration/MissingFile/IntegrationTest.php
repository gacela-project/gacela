<?php

declare(strict_types=1);

namespace GacelaTest\Integration\MissingFile;

use Gacela\ClassResolver\Config\ConfigNotFoundException;
use Gacela\ClassResolver\DependencyProvider\DependencyProviderNotFoundException;
use Gacela\ClassResolver\Factory\FactoryNotFoundException;
use Gacela\ClassResolver\Repository\RepositoryNotFoundException;
use Gacela\Container\Exception\ContainerKeyNotFoundException;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function testMissingFactoryModule(): void
    {
        $this->expectException(FactoryNotFoundException::class);

        $facade = new MissingFactoryFile\Facade();
        $facade->error();
    }

    public function testMissingConfigModule(): void
    {
        $this->expectException(ConfigNotFoundException::class);

        $facade = new MissingConfigFile\Facade();
        $facade->error();
    }

    public function testMissingRepositoryModule(): void
    {
        $this->expectException(RepositoryNotFoundException::class);

        $facade = new MissingRepositoryFile\Facade();
        $facade->error();
    }

    public function testMissingDependencyProviderModule(): void
    {
        $this->expectException(DependencyProviderNotFoundException::class);

        $facade = new MissingDependencyProviderFile\Facade();
        $facade->error();
    }

    public function testMissingContainerServiceKeyModule(): void
    {
        $this->expectException(ContainerKeyNotFoundException::class);

        $facade = new MissingContainerServiceKey\Facade();
        $facade->error();
    }

    public function testRemoveKeyFromContainer(): void
    {
        $this->expectException(ContainerKeyNotFoundException::class);

        $facade = new RemoveKeyFromContainer\Facade();
        $facade->error();
    }
}
