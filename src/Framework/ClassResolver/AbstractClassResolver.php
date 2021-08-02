<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\Config;
use Gacela\Framework\Config\GacelaJsonConfig;
use RuntimeException;
use function get_class;
use function in_array;
use function is_string;

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

        self::addGlobal(
            sprintf('\%s\%s\%s', ClassInfo::MODULE_NAME_ANONYMOUS, $contextName, $type),
            $resolvedClass
        );
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

        return end($callerClassParts);
    }

    public static function overrideExistingResolvedClass(string $className, object $resolvedClass): void
    {
        $key = self::getGlobalKeyFromClassName($className);

        self::addGlobal($key, $resolvedClass);
    }

    /**
     * @internal so the Locator can access to the global instances before creating a new instance
     */
    public static function getGlobalInstance(string $className): ?object
    {
        $key = self::getGlobalKeyFromClassName($className);

        return self::$cachedGlobalInstances[$key]
            ?? self::$cachedGlobalInstances['\\' . $key]
            ?? null;
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
            /** @psalm-suppress MixedMethodCall */
            return new $resolvedClassName(...$this->resolveDependencies($resolvedClassName));
        }

        return null;
    }

    private function resolveDependencies(string $resolvedClassName): array
    {
        /** @var GacelaJsonConfig $gacelaJsonConfig */

        return $gacelaJsonConfig->dependencies()[$resolvedClassName] ?? [];
    }
}

// gacela.json
//"dependencies" : {
//    "App\\Product\\ProductFactory": [
//        "App\\Shared\\Infrastructure\\Repository\\ProductRepository"
//    ]
//  }
