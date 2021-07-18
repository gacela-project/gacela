<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use RuntimeException;
use function in_array;

abstract class AbstractClassResolver
{
    private const ALLOWED_TYPES_FOR_ANONYMOUS_GLOBAL = ['Config', 'Factory', 'DependencyProvider'];

    /** @var array<string,null|object> */
    protected static array $cachedInstances = [];

    /** @var array<string,object> */
    private static array $cachedGlobalInstances = [];

    protected static ?ClassNameFinderInterface $classNameFinder = null;

    abstract public function resolve(object $callerClass): ?object;

    abstract protected function getResolvableType(): string;

    /**
     * @param object|string $context
     */
    public static function addAnonymousGlobal($context, string $type, object $resolvedClass): void
    {
        self::validateTypeForAnonymousGlobalRegistration($type);

        if (is_object($context)) {
            $callerClass = get_class($context);
            /** @var string[] $callerClassParts */
            $callerClassParts = explode('\\', ltrim($callerClass, '\\'));
            $contextName = end($callerClassParts);
        } else {
            $contextName = $context;
        }

        self::addGlobal(
            sprintf('\%s\%s\%s', ClassInfo::MODULE_NAME_ANONYMOUS, $contextName, $type),
            $resolvedClass
        );
    }

    private static function validateTypeForAnonymousGlobalRegistration(string $type): void
    {
        if (!in_array($type, self::ALLOWED_TYPES_FOR_ANONYMOUS_GLOBAL)) {
            throw new RuntimeException(
                "Type '$type' not allowed. Valid types: " . implode(', ', self::ALLOWED_TYPES_FOR_ANONYMOUS_GLOBAL)
            );
        }
    }

    private static function addGlobal(string $key, object $resolvedClass): void
    {
        self::$cachedGlobalInstances[$key] = $resolvedClass;
    }

    public function doResolve(object $callerClass): ?object
    {
        $classInfo = new ClassInfo($callerClass);
        $cacheKey = $this->getCacheKey($classInfo);

        if (isset(self::$cachedInstances[$cacheKey])) {
            return self::$cachedInstances[$cacheKey];
        }

        $resolvedClassName = $this->findClassName($classInfo);

        if (null === $resolvedClassName) {
            return $this->resolveGlobal($cacheKey);
        }

        self::$cachedInstances[$cacheKey] = $this->createInstance($resolvedClassName);

        return self::$cachedInstances[$cacheKey];
    }

    private function resolveGlobal(string $cacheKey): ?object
    {
        $resolvedClass = self::$cachedGlobalInstances[$cacheKey] ?? null;

        if (null === $resolvedClass) {
            return null;
        }

        self::$cachedInstances[$cacheKey] = $resolvedClass;

        return self::$cachedInstances[$cacheKey];
    }

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
        if (null === self::$classNameFinder) {
            self::$classNameFinder = (new ClassResolverFactory())->createClassNameFinder();
        }

        return self::$classNameFinder;
    }

    private function createInstance(string $resolvedClassName): ?object
    {
        if (class_exists($resolvedClassName)) {
            /** @psalm-suppress MixedMethodCall */
            return new $resolvedClassName();
        }

        return null;
    }
}
