<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\DependencyProvider\DependencyProviderResolver;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;

abstract class AbstractFactory
{
    use ConfigResolverAwareTrait;

    /** @var array<string,Container> */
    private static array $containers = [];

    /**
     * @internal
     */
    public static function resetCache(): void
    {
        self::$containers = [];
    }

    protected function getProvidedDependency(string $key): mixed
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
        $container = Container::withConfig(Config::getInstance());

        $dependencyProvider = $this->resolveDependencyProvider();
        $dependencyProvider->provideModuleDependencies($container);

        return $container;
    }

    /**
     * @throws ClassResolver\DependencyProvider\DependencyProviderNotFoundException
     */
    private function resolveDependencyProvider(): AbstractDependencyProvider
    {
        return (new DependencyProviderResolver())->resolve($this);
    }
}
