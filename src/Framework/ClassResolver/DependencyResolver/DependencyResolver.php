<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DependencyResolver;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use function is_callable;

final class DependencyResolver
{
    private GacelaConfigFileInterface $gacelaConfigFile;

    public function __construct(GacelaConfigFileInterface $gacelaConfigFile)
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
        /** @var ReflectionNamedType $paramType */
        $paramType = $parameter->getType();
        $type = $paramType->getName();

        if (!class_exists($type) && !interface_exists($type)) {
            return $parameter->getDefaultValue();
        }

        $reflection = new ReflectionClass($type);

        // If it's an interface we need to figure out which concrete class do we want to use
        if ($reflection->isInterface()) {
            $gacelaFileDependencies = $this->gacelaConfigFile->dependencies();
            $concreteClass = $gacelaFileDependencies[$reflection->getName()] ?? '';
            // a callable will be a way to bypass the instantiation and instead
            // use the result from the callable that was defined in the gacela config file.
            if (is_callable($concreteClass)) {
                return $concreteClass();
            }
            // if at this point there is no concrete class found for the interface we can
            // try one more thing looking for 1 concrete class that implements this interface
            if (empty($concreteClass)) {
                $concreteClass = $this->findConcreteClassThatImplements($reflection);
            }

            /** @var class-string $concreteClass */
            $reflection = new ReflectionClass($concreteClass);
        }

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
     * @return class-string|string
     */
    private function findConcreteClassThatImplements(ReflectionClass $interface): string
    {
        // TODO: Not implemented yet

        // Dummy solution: if the concrete class lives next to its interface, and with the same name.
        $concreteClass = str_replace('Interface', '', $interface->getName());
        if (!class_exists($concreteClass)) {
            $error = <<<TXT
No concrete class was found that implements:
{$interface->getName()}
Did you forget to map this interface to a concrete class in gacela.json using the 'dependencies' key?
TXT;
            throw new RuntimeException($error);
        }

        return $concreteClass;
    }
}
