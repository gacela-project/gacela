<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DependencyResolver;

use Composer\Autoload\ClassLoader;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use function count;
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
        $this->loadComposerAutoloaderClasses();

        $classes = get_declared_classes();
        $implementsInterface = [];
        foreach ($classes as $class) {
            $reflect = new ReflectionClass($class);
            if ($reflect->implementsInterface($interface->getName())) {
                $implementsInterface[] = $class;
            }
        }
        if (count($implementsInterface) > 1) {
            throw new RuntimeException(sprintf(
                'more than 1 concrete class implements %s',
                $interface->getName()
            ));
        }

        $concreteClass = reset($implementsInterface);
        if (!$concreteClass) {
            throw new RuntimeException(sprintf(
                'No concrete class was found that implements %s',
                $interface->getName()
            ));
        }

        return $concreteClass;
    }

    private function loadComposerAutoloaderClasses(): void
    {
        if (!empty(self::$requiredCached)) {
            return;
        }

        $classLoader = $this->getAutoloaderClassName();

        /** @var string $path */
        foreach ($classLoader->getClassMap() as $path) {
            if (isset(self::$requiredCached[$path])) {
                continue;
            }

            if ($this->canLoadRequiredCache($path)) {
                self::$requiredCached[$path] = true;
                /** @psalm-suppress UnresolvableInclude */
                require_once $path;
            }
        }
    }

    private function getAutoloaderClassName(): ClassLoader
    {
        $declaredClasses = get_declared_classes();
        foreach ($declaredClasses as $className) {
            if (strncmp($className, 'ComposerAutoloaderInit', 22) === 0) {
                /**
                 * @psalm-suppress MixedReturnStatement
                 * @psalm-suppress MixedMethodCall
                 *
                 * @var ClassLoader $classLoader
                 */
                $classLoader = $className::getLoader();
                return $classLoader;
            }
        }

        throw new RuntimeException('ComposerAutoloaderInit not found!');
    }

    private function canLoadRequiredCache(string $path): bool
    {
        $autoloadDependencies = $this->gacelaConfigFile->autoloadDependencies();
        foreach ($autoloadDependencies as $preLoadPath) {
            if (false !== stripos($path, $preLoadPath)) {
                return true;
            }
        }

        return false;
    }
}
