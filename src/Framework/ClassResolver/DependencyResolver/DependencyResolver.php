<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DependencyResolver;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use function is_callable;

final class DependencyResolver
{
    private GacelaConfigFile $gacelaConfigFile;

    public function __construct(GacelaConfigFile $gacelaConfigFile)
    {
        $this->gacelaConfigFile = $gacelaConfigFile;
    }

    /**
     * @param class-string $resolvedClassName
     *
     * @return list<mixed>
     */
    public function resolveDependencies(string $resolvedClassName): array
    {
        $reflection = new ReflectionClass($resolvedClassName);
        $constructor = $reflection->getConstructor();
        if (!$constructor) {
            return [];
        }

        /** @var list<mixed> $dependencies */
        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $paramType = $parameter->getType();
            if ($paramType) {
                /** @psalm-suppress MixedAssignment */
                $dependencies[] = $this->resolveDependenciesRecursively($parameter);
            }
        }

        return $dependencies;
    }

    /**
     * @return mixed
     */
    private function resolveDependenciesRecursively(ReflectionParameter $parameter)
    {
        if (!$parameter->hasType()) {
            throw new RuntimeException("No parameter type for '{$parameter->getName()}'");
        }

        /** @var ReflectionNamedType $paramType */
        $paramType = $parameter->getType();
        $paramTypeName = $paramType->getName();
        if (!class_exists($paramTypeName) && !interface_exists($paramTypeName)) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            /** @var ReflectionClass $reflectionClass */
            $reflectionClass = $parameter->getDeclaringClass();
            throw new RuntimeException("Unable to resolve [$parameter] from {$reflectionClass->getName()}");
        }

        /** @var mixed $mappedClass */
        $mappedClass = $this->gacelaConfigFile->getMappingInterface($paramTypeName);
        if (is_callable($mappedClass)) {
            return $mappedClass();
        }

        if (is_object($mappedClass)) {
            return $mappedClass;
        }

        $reflection = $this->resolveReflectionClass($paramTypeName);
        $constructor = $reflection->getConstructor();
        if (!$constructor) {
            return $reflection->newInstance();
        }

        /** @var list<mixed> $innerDependencies */
        $innerDependencies = [];

        foreach ($constructor->getParameters() as $constructorParameter) {
            $paramType = $constructorParameter->getType();
            if ($paramType) {
                /** @psalm-suppress MixedAssignment */
                $innerDependencies[] = $this->resolveDependenciesRecursively($constructorParameter);
            }
        }

        return $reflection->newInstanceArgs($innerDependencies);
    }

    /**
     * @param class-string $paramTypeName
     */
    private function resolveReflectionClass(string $paramTypeName): ReflectionClass
    {
        $reflection = new ReflectionClass($paramTypeName);

        if ($reflection->isInstantiable()) {
            return $reflection;
        }

        /** @var mixed $concreteClass */
        $concreteClass = $this->gacelaConfigFile->getMappingInterface($reflection->getName());

        if (null !== $concreteClass) {
            /** @var class-string $concreteClass */
            return new ReflectionClass($concreteClass);
        }

        throw DependencyResolverNotFoundException::forClassName($reflection->getName());
    }
}
