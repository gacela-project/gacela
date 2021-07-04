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
    protected ?ClassInfo $classInfo = null;

    abstract public function resolve(object $callerClass): ?object;

    /**
     * @return null|mixed
     */
    public function doResolve(object $callerClass)
    {
        $this->setCallerObject($callerClass);
        $cacheKey = $this->getCacheKey();

        if (isset(static::$cachedInstances[$cacheKey])) {
            return static::$cachedInstances[$cacheKey];
        }

        $resolvedClassName = $this->findClassName();

        if (null === $resolvedClassName) {
            return null;
        }

        self::$cachedInstances[$cacheKey] = $this->createInstance($resolvedClassName);

        return self::$cachedInstances[$cacheKey];
    }

    public function setCallerObject(object $callerClass): void
    {
        $this->classInfo = new ClassInfo($callerClass);
    }

    public function getClassInfo(): ClassInfo
    {
        assert($this->classInfo instanceof ClassInfo);

        return $this->classInfo;
    }

    abstract protected function getResolvableType(): string;

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
