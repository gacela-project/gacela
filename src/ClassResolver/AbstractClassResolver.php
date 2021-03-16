<?php

declare(strict_types=1);

namespace Gacela\ClassResolver;

use Gacela\ClassResolver\ClassNameFinder\ClassNameFinderInterface;

abstract class AbstractClassResolver
{
    protected const RESOLVABLE_TYPE = '';

    protected static ?ClassResolverConfig $classResolverConfig = null;
    protected static ?ClassResolverFactory $classResolverFactory = null;
    /** @var string[]|null */
    protected static ?array $resolvableTypeClassNamePatternMap = null;
    protected static ?ClassNameFinderInterface $classNameFinder = null;
    protected ?ClassInfo $classInfo = null;
    /** @var object[] */
    protected static array $cachedInstances = [];

    /**
     * @param object|string $callerClass
     *
     * @return object|null
     */
    abstract public function resolve($callerClass);

    /**
     * @param object|string $callerClass
     *
     * @return object|null
     */
    public function doResolve($callerClass)
    {
        $this->setCallerClass($callerClass);

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

    /**
     * @param object|string $callerClass
     */
    public function setCallerClass($callerClass): self
    {
        $this->classInfo = new ClassInfo();
        $this->classInfo->setClass($callerClass);

        return $this;
    }

    public function getClassInfo(): ClassInfo
    {
        assert($this->classInfo instanceof ClassInfo);

        return $this->classInfo;
    }

    protected function findCacheKey(): ?string
    {
        return $this->getCacheKey();
    }

    protected function resolveClassName(): ?string
    {
        return $this->findClassName();
    }

    protected function findClassName(): ?string
    {
        $classNamePattern = $this->getResolvableTypeClassNamePatternMap()[static::RESOLVABLE_TYPE] ?? null;

        if ($classNamePattern === null) {
            return null;
        }

        return $this->getClassNameFinder()->findClassName($this->getClassInfo()->getModule(), $classNamePattern);
    }

    /**
     * @return string[]
     */
    protected function getResolvableTypeClassNamePatternMap(): array
    {
        if (static::$resolvableTypeClassNamePatternMap === null) {
            static::$resolvableTypeClassNamePatternMap = $this->getClassResolverConfig()->getResolvableTypeClassNamePatternMap();
        }

        return static::$resolvableTypeClassNamePatternMap;
    }

    protected function getClassNameFinder(): ClassNameFinderInterface
    {
        if (static::$classNameFinder === null) {
            static::$classNameFinder = $this->getClassResolverFactory()->createClassNameFinder();
        }

        return static::$classNameFinder;
    }

    /**
     * @return object
     */
    protected function createInstance(string $resolvedClassName)
    {
        return new $resolvedClassName();
    }

    protected function getClassResolverConfig(): ClassResolverConfig
    {
        if (static::$classResolverConfig === null) {
            static::$classResolverConfig = new ClassResolverConfig();
        }

        return static::$classResolverConfig;
    }

    protected function getClassResolverFactory(): ClassResolverFactory
    {
        if (static::$classResolverFactory === null) {
            static::$classResolverFactory = new ClassResolverFactory();
            static::$classResolverFactory->setConfig($this->getClassResolverConfig());
        }

        return static::$classResolverFactory;
    }

    protected function getCacheKey(): string
    {
        assert($this->classInfo instanceof ClassInfo);

        return $this->classInfo->getCacheKey(static::RESOLVABLE_TYPE);
    }
}
