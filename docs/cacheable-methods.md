# Cacheable Facade Methods

Cache the result of a facade method for a given TTL using the `#[Cacheable]` attribute and `CacheableTrait`.

## Quick start

```php
use Gacela\Framework\Attribute\Cacheable;
use Gacela\Framework\Attribute\CacheableTrait;
use Gacela\Framework\AbstractFacade;

final class CatalogFacade extends AbstractFacade
{
    use CacheableTrait;

    #[Cacheable(ttl: 3600)]
    public function getPopularProducts(): array
    {
        return $this->cached(fn (): array =>
            $this->getFactory()->createRepository()->fetchPopular(),
        );
    }
}
```

Subsequent calls within the TTL return the cached value without invoking the callback.

## How it works

`#[Cacheable]` is metadata only. The real caching happens inside `$this->cached(...)`, which:

1. Reads the attribute via reflection (memoized per `Class::method`).
2. Builds a cache key from the class, method, and arguments.
3. Returns the cached value on hit, or runs the callback and stores the result on miss.

The method name and arguments are inferred from the caller's stack frame via `debug_backtrace()`. You don't pass them — but you can, for performance or when calling from a helper (see [Opting out of backtrace](#opting-out-of-backtrace) below).

## Arguments shape the cache key

Calls with different arguments are cached separately.

```php
#[Cacheable(ttl: 600)]
public function findUser(int $id): User
{
    return $this->cached(fn (): User =>
        $this->getFactory()->createRepository()->find($id),
    );
}

$facade->findUser(1); // runs callback, caches under key ending in "::1"
$facade->findUser(1); // cache hit
$facade->findUser(2); // runs callback, separate entry
```

Single `int` or `string` arguments become part of the key directly (`Facade::method::42`). Other types (arrays, objects, multiple args) fall back to `md5(serialize(...))`.

## Custom key templates

Use `key` with `{N}` placeholders to interpolate the Nth argument into the cache key — useful for shared keys across modules or for readable keys in an external cache.

```php
#[Cacheable(ttl: 3600, key: 'user:{0}')]
public function getUser(int $id): array
{
    return $this->cached(fn (): array =>
        $this->getFactory()->createRepository()->find($id),
    );
}
```

A bare string with no placeholders is args-agnostic — every call shares the same entry regardless of arguments. That's rarely what you want when the method takes parameters.

## Clearing the cache

```php
// Clear everything for this facade class
CatalogFacade::clearMethodCache();

// Clear all entries for a specific method (any args)
CatalogFacade::clearMethodCacheFor('getPopularProducts');
```

`clearMethodCacheFor()` matches on the exact `Class::method::` prefix. Passing `'get'` does **not** clear every method whose name starts with `get`.

## Pluggable storage backend

By default, cache lives in process memory via `InMemoryCacheStorage`. On PHP-FPM that means entries die with the request — fine for batch jobs and long-running workers, but effectively a no-op for typical web traffic.

Swap in any backend that implements `CacheStorageInterface` (e.g. APCu, Redis, a PSR-16 adapter):

```php
use Gacela\Framework\Attribute\CacheableConfig;

CacheableConfig::setStorage(new RedisCacheStorage($redis));
```

The interface is small:

```php
interface CacheStorageInterface
{
    public function has(string $key): bool;
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int $ttl): void;
    public function delete(string $key): void;
    public function clear(): void;
    public function deleteByPrefix(string $prefix): void;
}
```

Call `CacheableConfig::setStorage()` once at bootstrap. All facades using `CacheableTrait` share the same backend.

## TTL overrides per method

Override the TTL declared on the attribute without changing code — useful for tuning hot paths per environment.

```php
CacheableConfig::setTtlOverrides([
    CatalogFacade::class . '::getPopularProducts' => 60,   // tighten in staging
    UserFacade::class . '::getUser' => 86400,              // loosen in prod
]);
```

The override applies on the next `set()`; existing entries keep their original expiry until evicted.

## Opting out of backtrace

`cached()` calls `debug_backtrace()` (limit 2) to infer the method name and arguments. Cost is 1–5 µs — unmeasurable for typical "expensive" methods (DB, HTTP). Pass `$method` and `$args` explicitly when:

- The cached operation itself is very fast and the overhead matters.
- The method takes very large arguments (frame-construction cost scales with arg count).
- `cached()` is called from a private helper rather than the attributed method itself (backtrace would pick the helper, find no attribute, and skip caching).

```php
#[Cacheable(ttl: 3600)]
public function getUser(int $id): array
{
    return $this->cached(
        fn (): array => $this->getFactory()->createRepository()->find($id),
        __METHOD__,
        [$id],
    );
}
```

## Caching `null`

A method that returns `null` is cached correctly — repeated calls do **not** re-invoke the callback. `CacheableTrait` distinguishes "cached null" from "cache miss" via a sentinel, so `Optional`-style return types work as expected.

## Limitations

- **Per-process by default.** Entries in `InMemoryCacheStorage` do not survive the request on PHP-FPM. Use a shared backend (APCu, Redis) if you need cross-request caching.
- **Serialization.** The default key and miss detection rely on `serialize()` for non-scalar arguments. Arguments containing closures or resources cannot be serialized and will throw.
- **Memoized attribute metadata.** The `#[Cacheable]` attribute is reflected once per `Class::method` and cached for the lifetime of the process. Changing the attribute at runtime has no effect; change the code and redeploy.
