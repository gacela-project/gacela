<?php

declare(strict_types=1);

namespace Gacela\ClassResolver;

use Gacela\ClassResolver\ClassNameFinder\ClassNameFinderInterface;

abstract class AbstractClassResolver
{
    /** @var array<string,mixed> */
    protected static array $cachedInstances = [];

    protected static ?ClassResolverFactory $classResolverFactory = null;
    protected static ?ClassNameFinderInterface $classNameFinder = null;

    protected ?ClassInfo $classInfo = null;

    abstract public function resolve(object $callerClass): ?object;

    abstract protected function getResolvableType(): string;

    public function doResolve(object $callerClass): ?object
    {
        $this->setCallerObject($callerClass);
        $cacheKey = $this->getCacheKey();

        if (isset(static::$cachedInstances[$cacheKey])) {
            return static::$cachedInstances[$cacheKey];
        }

        $resolvedClassName = $this->findClassName();

        if ($resolvedClassName === null) {
            return null;
        }

        self::$cachedInstances[$cacheKey] = $this->createInstance($resolvedClassName);

        return self::$cachedInstances[$cacheKey];
    }

    public function setCallerObject(object $callerClass): void
    {
        $this->classInfo = new ClassInfo($callerClass);
    }

    private function getCacheKey(): string
    {
        assert($this->classInfo instanceof ClassInfo);

        return $this->classInfo->getCacheKey($this->getResolvableType());
    }

    private function findClassName(): ?string
    {
        return $this->getClassNameFinder()->findClassName(
            $this->getClassInfo(),
            $this->getResolvableType()
        );
    }

    private function getClassNameFinder(): ClassNameFinderInterface
    {
        if (static::$classNameFinder === null) {
            static::$classNameFinder = $this->getClassResolverFactory()->createClassNameFinder();
        }

        return static::$classNameFinder;
    }

    private function getClassResolverFactory(): ClassResolverFactory
    {
        if (static::$classResolverFactory === null) {
            static::$classResolverFactory = new ClassResolverFactory();
        }

        return static::$classResolverFactory;
    }

    public function getClassInfo(): ClassInfo
    {
        assert($this->classInfo instanceof ClassInfo);

        return $this->classInfo;
    }

    /**
     * @return object|null
     */
    private function createInstance(string $resolvedClassName)
    {
        if (class_exists($resolvedClassName)) {
            return new $resolvedClassName();
        }

        return null;
    }
}
