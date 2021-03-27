<?php

declare(strict_types=1);

namespace Gacela;

use Gacela\ClassResolver\DependencyProvider\DependencyProviderResolver;
use Gacela\Container\Container;
use Gacela\Container\Exception\ContainerException;
use Gacela\Container\Exception\ContainerKeyNotFoundException;

abstract class AbstractFactory
{
    use ConfigResolverAwareTrait;
    use RepositoryResolverAwareTrait;

    /** @var Container[] */
    private static array $containers = [];

    /**
     * @throws ContainerException
     * @throws ContainerKeyNotFoundException
     *
     * @return mixed
     */
    protected function getProvidedDependency(string $key)
    {
        $container = $this->getContainer();

        if ($container->has($key) === false) {
            throw new ContainerKeyNotFoundException($this, $key);
        }

        return $container->get($key);
    }

    private function getContainer(): Container
    {
        $containerKey = static::class;

        if (!isset(self::$containers[$containerKey])) {
            self::$containers[$containerKey] = $this->createContainerWithProvidedDependencies();
        }

        return self::$containers[$containerKey];
    }

    private function createContainerWithProvidedDependencies(): Container
    {
        $container = $this->createContainer();
        $dependencyProvider = $this->resolveDependencyProvider();
        $this->provideExternalDependencies($dependencyProvider, $container);

        return $container;
    }

    private function createContainer(): Container
    {
        return new Container();
    }

    private function resolveDependencyProvider(): AbstractDependencyProvider
    {
        return $this->createDependencyProviderResolver()->resolve($this);
    }

    private function createDependencyProviderResolver(): DependencyProviderResolver
    {
        return new DependencyProviderResolver();
    }

    private function provideExternalDependencies(AbstractDependencyProvider $dependencyProvider, Container $container): void
    {
        $dependencyProvider->provideModuleDependencies($container);
    }
}
