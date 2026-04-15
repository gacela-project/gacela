<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Closure;
use ReflectionMethod;
use stdClass;

use function count;
use function debug_backtrace;
use function end;
use function explode;
use function is_int;
use function is_scalar;
use function is_string;
use function md5;
use function preg_replace_callback;
use function serialize;
use function sprintf;
use function str_contains;

/**
 * Enables #[Cacheable] support for facade methods.
 *
 * Usage:
 * ```php
 * use Gacela\Framework\Attribute\Cacheable;
 * use Gacela\Framework\Attribute\CacheableTrait;
 *
 * final class MyFacade
 * {
 *     use CacheableTrait;
 *
 *     #[Cacheable(ttl: 3600)]
 *     public function getExpensiveData(int $id): array
 *     {
 *         return $this->cached(fn (): array =>
 *             $this->getFactory()->createRepository()->fetchData($id),
 *         );
 *     }
 * }
 * ```
 *
 * The method name and arguments are inferred from the caller's stack frame;
 * the #[Cacheable] attribute supplies TTL and (optionally) a custom key template.
 *
 * For hot paths, very-large arguments, or when `cached()` is invoked from a
 * helper (not the attributed method itself), pass `$method` and `$args`
 * explicitly to skip the `debug_backtrace()` call:
 *
 * ```php
 * return $this->cached(fn () => ..., __METHOD__, [$id]);
 * ```
 */
trait CacheableTrait
{
    /** @var array<string, Cacheable|false> */
    private static array $attributeCache = [];

    private static ?stdClass $cacheMissSentinel = null;

    public static function clearMethodCache(): void
    {
        CacheableConfig::getStorage()->clear();
    }

    public static function clearMethodCacheFor(string $method): void
    {
        CacheableConfig::getStorage()->deleteByPrefix(sprintf('%s::%s::', static::class, $method));
    }

    /**
     * @param list<mixed>|null $args
     */
    protected function cached(Closure $callback, ?string $method = null, ?array $args = null): mixed
    {
        if ($method === null) {
            $frame = debug_backtrace(0, 2)[1] ?? null;
            if ($frame === null || !isset($frame['function'])) {
                return $callback();
            }
            $method = (string) $frame['function'];
            /** @var list<mixed> $args */
            $args ??= $frame['args'] ?? [];
        } else {
            if (str_contains($method, '::')) {
                $parts = explode('::', $method);
                $method = (string) end($parts);
            }
            $args ??= [];
        }

        $attribute = $this->resolveCacheableAttribute($method);
        if ($attribute === null) {
            return $callback();
        }

        $storage = CacheableConfig::getStorage();
        $cacheKey = $this->buildCacheKey($method, $args, $attribute);

        $miss = self::$cacheMissSentinel ??= new stdClass();
        $cached = $storage->get($cacheKey, $miss);
        if ($cached !== $miss) {
            return $cached;
        }

        $result = $callback();
        $ttl = CacheableConfig::resolveTtl(sprintf('%s::%s', static::class, $method), $attribute->ttl);
        $storage->set($cacheKey, $result, $ttl);

        return $result;
    }

    private function resolveCacheableAttribute(string $method): ?Cacheable
    {
        $cacheKey = static::class . '::' . $method;
        if (!isset(self::$attributeCache[$cacheKey])) {
            $attributes = (new ReflectionMethod($this, $method))->getAttributes(Cacheable::class);
            self::$attributeCache[$cacheKey] = $attributes === [] ? false : $attributes[0]->newInstance();
        }

        $entry = self::$attributeCache[$cacheKey];
        return $entry === false ? null : $entry;
    }

    /**
     * @param list<mixed> $args
     */
    private function buildCacheKey(string $method, array $args, Cacheable $attribute): string
    {
        if ($attribute->key !== null) {
            return $this->interpolateKey($attribute->key, $args);
        }

        return sprintf('%s::%s::%s', static::class, $method, $this->hashArgs($args));
    }

    /**
     * @param list<mixed> $args
     */
    private function hashArgs(array $args): string
    {
        if ($args === []) {
            return 'no-args';
        }
        if (count($args) === 1) {
            $first = $args[0];
            if (is_int($first) || is_string($first)) {
                return (string) $first;
            }
        }
        return md5(serialize($args));
    }

    /**
     * Replace `{N}` placeholders in the key template with the Nth caller argument.
     *
     * @param list<mixed> $args
     */
    private function interpolateKey(string $template, array $args): string
    {
        return (string) preg_replace_callback(
            '/\{(\d+)\}/',
            static function (array $match) use ($args): string {
                $index = (int) $match[1];
                $value = $args[$index] ?? '';
                if ($value === null || is_scalar($value)) {
                    return (string) $value;
                }
                return md5(serialize($value));
            },
            $template,
        );
    }
}
