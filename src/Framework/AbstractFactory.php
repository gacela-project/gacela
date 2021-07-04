<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\DependencyProvider\DependencyProviderResolver;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\Exception\ContainerKeyNotFoundException;

abstract class AbstractFactory
{
    use ConfigResolverAwareTrait;

    /** @var Container[] */
    private static array $containers = [];

    /**
     * @throws ContainerKeyNotFoundException
     *
     * @return mixed
     */
    protected function getProvidedDependency(string $key)
    {
        $container = $this->getContainer();

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
        $container = new Container();
        $dependencyProvider = $this->resolveDependencyProvider();
        $dependencyProvider->provideModuleDependencies($container);

        return $container;
    }

    private function resolveDependencyProvider(): AbstractDependencyProvider
    {
        return $this->createDependencyProviderResolver()->resolve($this);
    }

    private function createDependencyProviderResolver(): DependencyProviderResolver
    {
        return new DependencyProviderResolver();
    }
}
