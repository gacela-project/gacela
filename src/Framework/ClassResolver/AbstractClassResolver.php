<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;

abstract class AbstractClassResolver
{
    /** @var array<string,mixed|object> */
    protected static array $cachedInstances = [];
    protected static ?ClassResolverFactory $classResolverFactory = null;
    protected static ?ClassNameFinderInterface $classNameFinder = null;

    abstract public function resolve(object $callerClass): ?object;

    /**
     * @return null|mixed
     */
    public function doResolve(object $callerClass)
    {
        $classInfo = new ClassInfo($callerClass);
        $cacheKey = $this->getCacheKey($classInfo);

        if (isset(static::$cachedInstances[$cacheKey])) {
            return static::$cachedInstances[$cacheKey];
        }

        $resolvedClassName = $this->findClassName($classInfo);

        if (null === $resolvedClassName) {
            return null;
        }

        self::$cachedInstances[$cacheKey] = $this->createInstance($resolvedClassName);

        return self::$cachedInstances[$cacheKey];
    }

    abstract protected function getResolvableType(): string;

    private function getCacheKey(ClassInfo $classInfo): string
    {
        return $classInfo->getCacheKey($this->getResolvableType());
    }

    private function findClassName(ClassInfo $classInfo): ?string
    {
        return $this->getClassNameFinder()->findClassName(
            $classInfo,
            $this->getResolvableType()
        );
    }

    private function getClassNameFinder(): ClassNameFinderInterface
    {
        if (null === static::$classNameFinder) {
            static::$classNameFinder = $this->getClassResolverFactory()->createClassNameFinder();
        }

        return static::$classNameFinder;
    }

    private function getClassResolverFactory(): ClassResolverFactory
    {
        if (null === static::$classResolverFactory) {
            static::$classResolverFactory = new ClassResolverFactory();
        }

        return static::$classResolverFactory;
    }

    /**
     * @return null|object
     */
    private function createInstance(string $resolvedClassName)
    {
        if (class_exists($resolvedClassName)) {
            /** @psalm-suppress MixedMethodCall */
            return new $resolvedClassName();
        }

        return null;
    }
}
