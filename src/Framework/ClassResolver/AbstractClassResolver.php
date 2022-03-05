<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\DependencyResolver\DependencyResolver;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Config;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use function class_exists;
use function is_string;

abstract class AbstractClassResolver
{
    /** @var array<string,null|object> */
    private static array $cachedInstances = [];

    private ?ClassNameFinderInterface $classNameFinder = null;

    private ?DependencyResolver $dependencyResolver = null;

    private ?GacelaConfigFile $gacelaFileConfig = null;

    abstract public function resolve(object $callerClass): ?object;

    abstract protected function getResolvableType(): string;

    public function doResolve(object $callerClass): ?object
    {
        $classInfo = ClassInfo::fromObject($callerClass, $this->getResolvableType());
        $cacheKey = $classInfo->getCacheKey();

        $resolvedClass = $this->resolveCached($cacheKey);
        if (null !== $resolvedClass) {
            return $resolvedClass;
        }

        $resolvedClassName = $this->findClassName($classInfo);
        if (null === $resolvedClassName) {
            return null;
        }

        self::$cachedInstances[$cacheKey] = $this->createInstance($resolvedClassName);

        return self::$cachedInstances[$cacheKey];
    }

    private function resolveCached(string $cacheKey): ?object
    {
        return AnonymousGlobal::getByKey($cacheKey)
            ?? self::$cachedInstances[$cacheKey]
            ?? null;
    }

    private function findClassName(ClassInfo $classInfo): ?string
    {
        return $this->getClassNameFinder()->findClassName(
            $classInfo,
            $this->getFinalResolvableTypes()
        );
    }

    private function getClassNameFinder(): ClassNameFinderInterface
    {
        if (null === $this->classNameFinder) {
            $this->classNameFinder = (new ClassResolverFactory())
                ->createClassNameFinder();
        }

        return $this->classNameFinder;
    }

    /**
     * Allow overriding gacela resolvable types.
     *
     * @return list<string>
     */
    private function getFinalResolvableTypes(): array
    {
        $overrideResolvableTypes = $this->getGacelaConfigFile()->getOverrideResolvableTypes();

        $overrideResolvable = $overrideResolvableTypes[$this->getResolvableType()] ?? $this->getResolvableType();

        if (is_string($overrideResolvable)) {
            $overrideResolvable = [$overrideResolvable];
        }

        return $overrideResolvable;
    }

    private function createInstance(string $resolvedClassName): ?object
    {
        if (class_exists($resolvedClassName)) {
            $dependencies = $this->getDependencyResolver()
                ->resolveDependencies($resolvedClassName);

            /** @psalm-suppress MixedMethodCall */
            return new $resolvedClassName(...$dependencies);
        }

        return null;
    }

    private function getDependencyResolver(): DependencyResolver
    {
        if (null === $this->dependencyResolver) {
            $this->dependencyResolver = new DependencyResolver(
                $this->getGacelaConfigFile()
            );
        }

        return $this->dependencyResolver;
    }

    private function getGacelaConfigFile(): GacelaConfigFile
    {
        if (null === $this->gacelaFileConfig) {
            $this->gacelaFileConfig = Config::getInstance()
                ->getFactory()
                ->createGacelaConfigFileFactory()
                ->createGacelaFileConfig();
        }

        return $this->gacelaFileConfig;
    }
}
