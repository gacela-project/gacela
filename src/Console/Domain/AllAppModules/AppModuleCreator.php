<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use Gacela\Framework\ClassResolver\Config\ConfigNotFoundException;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\DependencyProvider\DependencyProviderNotFoundException;
use Gacela\Framework\ClassResolver\DependencyProvider\DependencyProviderResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryNotFoundException;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use ReflectionClass;

final class AppModuleCreator
{
    public function __construct(
        private FactoryResolver $factoryResolver,
        private ConfigResolver $configResolver,
        private DependencyProviderResolver $dependencyProviderResolver,
    ) {
    }

    /**
     * @param class-string $facadeClass
     */
    public function fromClass(string $facadeClass): AppModule
    {
        return new AppModule(
            $this->moduleName($facadeClass),
            $facadeClass,
            $this->findFactory($facadeClass),
            $this->findConfig($facadeClass),
            $this->findDependencyProvider($facadeClass),
        );
    }

    /**
     * @param class-string $facadeClass
     */
    private function moduleName(string $facadeClass): string
    {
        $parts = explode('\\', $facadeClass);
        array_pop($parts);

        return (string)end($parts);
    }

    /**
     * @param class-string $facadeClass
     */
    private function findFactory(string $facadeClass): ?string
    {
        try {
            $resolver = $this->factoryResolver->resolve($facadeClass);

            if ((new ReflectionClass($resolver))->isAnonymous()) {
                throw new FactoryNotFoundException($facadeClass);
            }

            return $resolver::class;
        } catch (FactoryNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param class-string $facadeClass
     */
    private function findConfig(string $facadeClass): ?string
    {
        try {
            $resolver = $this->configResolver->resolve($facadeClass);

            if ((new ReflectionClass($resolver))->isAnonymous()) {
                throw new ConfigNotFoundException($facadeClass);
            }

            return $resolver::class;
        } catch (ConfigNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param class-string $facadeClass
     */
    private function findDependencyProvider(string $facadeClass): ?string
    {
        try {
            $resolver = $this->dependencyProviderResolver->resolve($facadeClass);

            if ((new ReflectionClass($resolver))->isAnonymous()) {
                throw new DependencyProviderNotFoundException($resolver);
            }
            return $resolver::class;
        } catch (DependencyProviderNotFoundException $e) {
            return null;
        }
    }
}
