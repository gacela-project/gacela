<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\DependencyResolver\DependencyResolver;
use Gacela\Framework\Config;
use RuntimeException;
use function get_class;
use function in_array;
use function is_string;
use function sprintf;

abstract class AbstractClassResolver
{
    private const ALLOWED_TYPES_FOR_ANONYMOUS_GLOBAL = ['Config', 'Factory', 'DependencyProvider'];

    /** @var array<string,null|object> */
    private static array $cachedInstances = [];

    /** @var array<string,object> */
    private static array $cachedGlobalInstances = [];

    private static ?ClassNameFinderInterface $classNameFinder = null;

    private ?DependencyResolver $dependencyResolver = null;

    abstract public function resolve(object $callerClass): ?object;

    abstract protected function getResolvableType(): string;

    /**
     * Add an anonymous class as 'Config', 'Factory' or 'DependencyProvider' as a global resource
     * bound to the context that it's pass as first argument. It can be the string-key
     * (from a non-class/file context) or the class/object itself.
     *
     * @param object|string $context
     */
    public static function addAnonymousGlobal($context, object $resolvedClass): void
    {
        $contextName = self::extractContextNameFromContext($context);
        $parentClass = get_parent_class($resolvedClass);

        $type = is_string($parentClass)
            ? ResolvableType::fromClassName($parentClass)->resolvableType()
            : $contextName;

        self::validateTypeForAnonymousGlobalRegistration($type);

        $key = sprintf('\%s\%s\%s', ClassInfo::MODULE_NAME_ANONYMOUS, $contextName, $type);
        self::addCachedGlobalInstance($key, $resolvedClass);
    }

    /**
     * @param object|string $context
     */
    private static function extractContextNameFromContext($context): string
    {
        if (is_string($context)) {
            return $context;
        }

        $callerClass = get_class($context);
        /** @var list<string> $callerClassParts */
        $callerClassParts = explode('\\', ltrim($callerClass, '\\'));

        $lastCallerClassParts = end($callerClassParts);

        return is_string($lastCallerClassParts) ? $lastCallerClassParts : '';
    }

    public static function overrideExistingResolvedClass(string $className, object $resolvedClass): void
    {
        $key = self::getGlobalKeyFromClassName($className);

        self::addCachedGlobalInstance($key, $resolvedClass);
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @internal so the Locator can access to the global instances before creating a new instance
     *
     * @return ?T
     */
    public static function getCachedGlobalInstance(string $className)
    {
        $key = self::getGlobalKeyFromClassName($className);

        /** @var ?T $instance */
        $instance = self::$cachedGlobalInstances[$key]
            ?? self::$cachedGlobalInstances['\\' . $key]
            ?? null;

        return $instance;
    }

    private static function getGlobalKeyFromClassName(string $className): string
    {
        return GlobalKey::fromClassName($className);
    }

    private static function validateTypeForAnonymousGlobalRegistration(string $type): void
    {
        if (!in_array($type, self::ALLOWED_TYPES_FOR_ANONYMOUS_GLOBAL)) {
            throw new RuntimeException(
                "Type '$type' not allowed. Valid types: " . implode(', ', self::ALLOWED_TYPES_FOR_ANONYMOUS_GLOBAL)
            );
        }
    }

    private static function addCachedGlobalInstance(string $key, object $resolvedClass): void
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

        $resolvedClass = $this->resolveGlobal($cacheKey);
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
            $gacelaFileConfig = Config::getInstance()
                ->getFactory()
                ->createGacelaConfigFileFactory()
                ->createGacelaFileConfig();

            $this->dependencyResolver = new DependencyResolver($gacelaFileConfig);
        }

        return $this->dependencyResolver;
    }
}
