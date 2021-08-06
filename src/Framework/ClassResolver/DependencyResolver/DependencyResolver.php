<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DependencyResolver;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use function is_callable;

final class DependencyResolver
{
    /** @var array<string,bool> */
    private static array $requiredCached = [];

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
        foreach ($constructor->getParameters() as $dependency) {
            $paramType = $dependency->getType();
            if ($paramType) {
                /**
                 * @var class-string $name
                 * @var ReflectionNamedType $paramType
                 */
                $name = $paramType->getName();

                /** @psalm-suppress MixedAssignment */
                $dependencies[] = $this->resolveDependenciesRecursively($name);
            }
        }

        return $dependencies;
    }

    /**
     * @param class-string|string $type
     *
     * @return mixed
     */
    private function resolveDependenciesRecursively(string $type)
    {
        if (!class_exists($type) && !interface_exists($type)) {
            return ($type === 'array') ? [] : $type;
        }

        $reflection = new ReflectionClass($type);

        if ($reflection->isInterface()) {
            $gacelaFileDependencies = $this->gacelaConfigFile->dependencies();
            $concreteClass = $gacelaFileDependencies[$reflection->getName()] ?? '';
            if (is_callable($concreteClass)) {
                return $concreteClass();
            }

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

        /** @var list<mixed> $dependencies */
        $dependencies = [];

        $params = $constructor->getParameters();
        foreach ($params as $param) {
            $paramType = $param->getType();
            if ($paramType) {
                /**
                 * @psalm-suppress UndefinedMethod
                 *
                 * @var ReflectionNamedType $paramType
                 * @var class-string $name
                 */
                $name = $paramType->getName();
                /** @psalm-suppress MixedAssignment */
                $dependencies[] = $this->resolveDependenciesRecursively($name);
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * @return class-string|string
     */
    private function findConcreteClassThatImplements(ReflectionClass $interface): string
    {
        // TODO: Not implemented yet

        // Dummy solution
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
