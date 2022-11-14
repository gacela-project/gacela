<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Closure;
use Gacela\Framework\ClassResolver\DependencyProvider\DependencyProviderResolver;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\Exception\ContainerKeyNotFoundException;

abstract class AbstractFactory
{
    use ConfigResolverAwareTrait;

    /** @var array<string,Container> */
    private static array $containers = [];

    /**
     * @throws ContainerKeyNotFoundException
     *
     * @return mixed
     */
    protected function getProvidedDependency(string $key)
    {
        return $this->getContainer()->get($key);
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
        $container = new Container($this->getServicesToExtend());
        $dependencyProvider = $this->resolveDependencyProvider();
        $dependencyProvider->provideModuleDependencies($container);

        return $container;
    }

    /**
     * @return array<string,list<Closure>>
     */
    private function getServicesToExtend(): array
    {
        return Config::getInstance()
            ->getSetupGacela()
            ->getServicesToExtend();
    }

    /**
     * @throws ClassResolver\DependencyProvider\DependencyProviderNotFoundException
     */
    private function resolveDependencyProvider(): AbstractDependencyProvider
    {
        return (new DependencyProviderResolver())->resolve($this);
    }
}
