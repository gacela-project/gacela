<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Provider\DependencyProviderResolver;
use Gacela\Framework\ClassResolver\Provider\ProviderNotFoundException;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;

/**
 * Base class for module factories.
 *
 * @template TConfig of AbstractConfig
 *
 * @implements ConfigAccessorInterface<TConfig>
 */
abstract class AbstractFactory implements ServiceFactoryInterface, ConfigAccessorInterface, ProviderAccessorInterface
{
    /** @use ConfigResolverAwareTrait<TConfig> */
    use ConfigResolverAwareTrait;

    /** @var array<string,Container> */
    private static array $containers = [];

    /** @var array<string,mixed> */
    private array $instances = [];

    /**
     * @internal
     */
    public static function resetCache(): void
    {
        self::$containers = [];
    }

    /**
     * Create or retrieve a singleton instance.
     *
     * @template TService
     *
     * @param string $key Unique identifier for the service
     * @param callable(): TService $creator Factory function to create the service
     *
     * @return TService
     */
    public function singleton(string $key, callable $creator): mixed
    {
        return $this->instances[$key] ??= $creator();
    }

    /**
     * Get a dependency provided by the module provider.
     *
     * @param string $key The dependency key
     *
     * @psalm-suppress MixedInferredReturnType
     * @psalm-suppress MixedReturnStatement
     */
    public function getProvidedDependency(string $key): mixed
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
