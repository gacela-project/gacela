<?php

declare(strict_types=1);

namespace Gacela\ClassResolver;

use Gacela\ClassResolver\ClassNameFinder\ClassNameFinderInterface;

abstract class AbstractClassResolver
{
    protected const RESOLVABLE_TYPE = '';

    protected static ?ClassResolverFactory $classResolverFactory = null;

    protected static ?ClassNameFinderInterface $classNameFinder = null;
    protected ?ClassInfo $classInfo = null;
    /** @var object[] */
    protected static array $cachedInstances = [];

    abstract public function resolve(object $callerClass): ?object;

    public function doResolve(object $callerClass): ?object
    {
        $this->setCallerObject($callerClass);

        $cacheKey = $this->findCacheKey();

        if ($cacheKey !== null && isset(static::$cachedInstances[$cacheKey])) {
            return static::$cachedInstances[$cacheKey];
        }
        $resolvedClassName = $this->resolveClassName();
        if ($resolvedClassName !== null) {
            $resolvedInstance = $this->createInstance($resolvedClassName);
            if ($cacheKey !== null) {
                static::$cachedInstances[$cacheKey] = $resolvedInstance;
            }
            return $resolvedInstance;
        }

        return null;
    }

    public function setCallerObject(object $callerClass): self
    {
        $this->classInfo = new ClassInfo($callerClass);

        return $this;
    }

    public function getClassInfo(): ClassInfo
    {
        assert($this->classInfo instanceof ClassInfo);

        return $this->classInfo;
    }

    private function findCacheKey(): ?string
    {
        return $this->getCacheKey();
    }

    private function resolveClassName(): ?string
    {
        return $this->findClassName();
    }

    private function findClassName(): ?string
    {
        return $this->getClassNameFinder()->findClassName($this->getClassInfo(), static::RESOLVABLE_TYPE);
    }

    private function getClassNameFinder(): ClassNameFinderInterface
    {
        if (static::$classNameFinder === null) {
            static::$classNameFinder = $this->getClassResolverFactory()->createClassNameFinder();
        }

        return static::$classNameFinder;
    }

    /**
     * @return object
     */
    private function createInstance(string $resolvedClassName)
    {
        return new $resolvedClassName();
    }

    private function getClassResolverFactory(): ClassResolverFactory
    {
        if (static::$classResolverFactory === null) {
            static::$classResolverFactory = new ClassResolverFactory();
        }

        return static::$classResolverFactory;
    }

    protected function getCacheKey(): string
    {
        assert($this->classInfo instanceof ClassInfo);

        return $this->classInfo->getCacheKey(static::RESOLVABLE_TYPE);
    }
}
