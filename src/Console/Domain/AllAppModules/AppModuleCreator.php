<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\DependencyProvider\DependencyProviderNotFoundException;
use Gacela\Framework\ClassResolver\DependencyProvider\DependencyProviderResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use ReflectionClass;

use function strlen;

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
            $this->fullModuleName($facadeClass),
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
    private function fullModuleName(string $facadeClass): string
    {
        $moduleNameIndex = strrpos($facadeClass, '\\') ?: strlen($facadeClass);

        return substr($facadeClass, 0, $moduleNameIndex);
    }

    /**
     * @param class-string $facadeClass
     */
    private function moduleName(string $facadeClass): string
    {
        $fullModuleName = $this->fullModuleName($facadeClass);

        $moduleName = strrchr($fullModuleName, '\\') ?: $fullModuleName;

        return ltrim($moduleName, '\\');
    }

    /**
     * @param class-string $facadeClass
     */
    private function findFactory(string $facadeClass): ?string
    {
        $resolver = $this->factoryResolver->resolve($facadeClass);

        if ((new ReflectionClass($resolver))->isAnonymous()) {
            return null;
        }

        return $resolver::class;
    }

    /**
     * @param class-string $facadeClass
     */
    private function findConfig(string $facadeClass): ?string
    {
        $resolver = $this->configResolver->resolve($facadeClass);

        if ((new ReflectionClass($resolver))->isAnonymous()) {
            return null;
        }

        return $resolver::class;
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
