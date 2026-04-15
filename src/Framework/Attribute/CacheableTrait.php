<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Closure;
use ReflectionMethod;

use function debug_backtrace;
use function is_scalar;
use function md5;
use function preg_replace_callback;
use function serialize;
use function sprintf;

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

        if ($storage->has($cacheKey)) {
            return $storage->get($cacheKey);
        }

        $result = $callback();
        $ttl = CacheableConfig::resolveTtl(sprintf('%s::%s', static::class, $method), $attribute->ttl);
        $storage->set($cacheKey, $result, $ttl);

        return $result;
    }

    private function resolveCacheableAttribute(string $method): ?Cacheable
    {
        $attributes = (new ReflectionMethod($this, $method))->getAttributes(Cacheable::class);
        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @param list<mixed> $args
     */
    private function buildCacheKey(string $method, array $args, Cacheable $attribute): string
    {
        if ($attribute->key !== null) {
            return $this->interpolateKey($attribute->key, $args);
        }

        $argsKey = $args !== [] ? md5(serialize($args)) : 'no-args';
        return sprintf('%s::%s::%s', static::class, $method, $argsKey);
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
