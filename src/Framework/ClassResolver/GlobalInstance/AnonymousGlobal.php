<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\GlobalInstance;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\GlobalKey;
use Gacela\Framework\ClassResolver\ResolvableType;
use RuntimeException;

use function in_array;
use function is_string;
use function sprintf;

/**
 * @internal
 */
final class AnonymousGlobal
{
    private const ALLOWED_TYPES_FOR_ANONYMOUS_GLOBAL = [
        'Config',
        'Factory',
        'Provider',
    ];

    /** @var array<string,object> */
    private static array $cachedGlobalInstances = [];

    /**
     * @internal
     */
    public static function resetCache(): void
    {
        self::$cachedGlobalInstances = [];
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return ?T
     *
     * @internal so the Locator can access to the global instances before creating a new instance
     */
    public static function getByClassName(string $className)
    {
        $key = self::getGlobalKeyFromClassName($className);

        /** @var ?T $instance */
        $instance = self::getByKey($key) // @phpstan-ignore-line
            ?? self::getByKey('\\' . $key)
            ?? null;

        return $instance;
    }

    public static function getByKey(string $key): ?object
    {
        return self::$cachedGlobalInstances[$key] ?? null;
    }

    /**
     * Add an anonymous class as 'Config', 'Factory' or 'Provider' as a global resource
     * bound to the context that it's pass as first argument. It can be the string-key
     * (from a non-class/file context) or the class/object itself.
     */
    public static function addGlobal(object|string $context, object $resolvedClass): void
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

    public static function overrideExistingResolvedClass(string $className, object $resolvedClass): void
    {
        $key = self::getGlobalKeyFromClassName($className);

        self::addCachedGlobalInstance($key, $resolvedClass);
    }

    private static function extractContextNameFromContext(object|string $context): string
    {
        if (is_string($context)) {
            return $context;
        }

        $callerClass = $context::class;
        /** @var list<string> $callerClassParts */
        $callerClassParts = explode('\\', ltrim($callerClass, '\\'));

        $lastCallerClassParts = end($callerClassParts);

        return is_string($lastCallerClassParts) ? $lastCallerClassParts : '';
    }

    private static function validateTypeForAnonymousGlobalRegistration(string $type): void
    {
        if (!in_array($type, self::ALLOWED_TYPES_FOR_ANONYMOUS_GLOBAL, true)) {
            throw new RuntimeException(
                sprintf("Type '%s' not allowed. Valid types: ", $type) . implode(
                    ', ',
                    self::ALLOWED_TYPES_FOR_ANONYMOUS_GLOBAL,
                ),
            );
        }
    }

    private static function addCachedGlobalInstance(string $key, object $resolvedClass): void
    {
        self::$cachedGlobalInstances[$key] = $resolvedClass;
    }

    private static function getGlobalKeyFromClassName(string $className): string
    {
        return GlobalKey::fromClassName($className);
    }
}
