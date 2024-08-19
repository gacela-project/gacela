<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Provider\DependencyProviderResolver;
use Gacela\Framework\ClassResolver\Provider\ProviderNotFoundException;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
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

        $resolver = (new ProviderResolver())->resolve($this);
        $resolver?->provideModuleDependencies($container);

        {
            // Temporal solution to keep BC with the AbstractDependencyProvider
            $dpResolver = (new DependencyProviderResolver())->resolve($this);
            $dpResolver?->provideModuleDependencies($container);

            if (!$resolver instanceof AbstractProvider && !$dpResolver instanceof AbstractProvider) {
                throw new ProviderNotFoundException(static::class);
            }
        }

        return $container;
    }
}
