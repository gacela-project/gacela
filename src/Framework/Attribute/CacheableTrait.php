<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use ReflectionClass;
use ReflectionMethod;

use function md5;
use function serialize;
use function time;

/**
 * Trait that enables #[Cacheable] attribute support for facade methods.
 *
 * Add this trait to your facade to automatically cache methods marked with #[Cacheable].
 */
trait CacheableTrait
{
    /** @var array<string,array{result:mixed,expires:int}> */
    private static array $methodCache = [];

    /**
     * Clear all cached method results.
     */
    public static function clearMethodCache(): void
    {
        self::$methodCache = [];
    }

    /**
     * Clear cached result for a specific method.
     */
    public static function clearMethodCacheFor(string $method): void
    {
        foreach (array_keys(self::$methodCache) as $key) {
            if (str_contains($key, $method)) {
                unset(self::$methodCache[$key]);
            }
        }
    }

    /**
     * Execute a method with caching support based on #[Cacheable] attribute.
     *
     * @param list<mixed> $args
     *
     * @return mixed
     */
    protected function cached(string $method, array $args, callable $callback): mixed
    {
        $reflectionMethod = $this->getReflectionMethod($method);
        $cacheableAttr = $this->getCacheableAttribute($reflectionMethod);

        if ($cacheableAttr === null) {
            return $callback();
        }

        $cacheKey = $this->generateCacheKey($method, $args, $cacheableAttr);
        $currentTime = time();

        // Check if we have a valid cached value
        if (isset(self::$methodCache[$cacheKey])) {
            $cached = self::$methodCache[$cacheKey];
            if ($cached['expires'] > $currentTime) {
                return $cached['result'];
            }

            // Expired, remove it
            unset(self::$methodCache[$cacheKey]);
        }

        // Execute the method and cache the result
        $result = $callback();
        self::$methodCache[$cacheKey] = [
            'result' => $result,
            'expires' => $currentTime + $cacheableAttr->ttl,
        ];

        return $result;
    }

    /**
     * Get reflection method for the given method name.
     */
    private function getReflectionMethod(string $method): ReflectionMethod
    {
        // Extract just the method name if it's a full class::method string
        $methodName = str_contains($method, '::') ? substr($method, (int)strrpos($method, '::') + 2) : $method;

        return (new ReflectionClass($this))->getMethod($methodName);
    }

    /**
     * Get Cacheable attribute from reflection method.
     */
    private function getCacheableAttribute(ReflectionMethod $method): ?Cacheable
    {
        $attributes = $method->getAttributes(Cacheable::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Generate a cache key for the method call.
     *
     * @param list<mixed> $args
     */
    private function generateCacheKey(string $method, array $args, Cacheable $attribute): string
    {
        if ($attribute->key !== null) {
            return $attribute->key;
        }

        $className = static::class;
        $argsKey = $args !== [] ? md5(serialize($args)) : 'no-args';

        return "{$className}::{$method}::{$argsKey}";
    }
}
