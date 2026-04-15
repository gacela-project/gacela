# Caching

Gacela caches at three different levels. Each solves a different problem — they compose, they don't replace one another.

| Layer | What it caches | Where | Typical use |
|---|---|---|---|
| [Framework resolution](#layer-1--framework-resolution-cache) | Resolved facades, factories, configs, merged config | Memory or disk | Always on — pick the mode per environment |
| [Cacheable methods](#layer-2--cacheable-facade-methods) | Return values of facade methods | Memory (pluggable) | Expensive, deterministic reads |
| [Value primitives](#layer-3--value-primitives) | Arbitrary key → value data, optionally with a dependency graph | Disk | Your code needs its own cache (compilers, pipelines, parsed artefacts) |

## Layer 1 — Framework resolution cache

Gacela resolves classes by convention: `Facade` → `Factory` → `Provider` → `Config`. Those lookups walk namespaces and files, and the merged configuration is reassembled from every `config/*.php` file. All of it is memoised once per process, and can additionally be persisted to disk between runs.

- **In-memory** (default): `InMemoryCache` holds resolved class names for the life of the process.
- **On-disk**: `ClassNamePhpCache` and `CustomServicesPhpCache` persist the same data to `gacela-class-names.php` / `gacela-custom-services.php`; `MergedConfigCache` persists the merged configuration to `gacela-merged-config[{env}].php`.

Configure at bootstrap:

```php
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;

Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
    $config->enableFileCache();                  // use the default cache dir
    // $config->enableFileCache('/custom/dir');  // or pick one
    // $config->setFileCache(false);             // explicitly off
    // $config->resetInMemoryCache();            // wipe static caches (tests)
});
```

The directory can also be overridden at runtime with the `GACELA_CACHE_DIR` environment variable — handy when the same image is reused across environments.

Typical wiring:

- **Development** — file cache **off**. Edits take effect immediately.
- **Production** — file cache **on**, directory baked into the image. Re-deploy to refresh.
- **Tests** — call `resetInMemoryCache()` between suites so resolution state doesn't bleed.

See also: [Opcache preload](opcache-preload.md) for getting PHP itself to cache Gacela's own source files.

## Layer 2 — Cacheable facade methods

Cache the *result* of a facade method with the `#[Cacheable]` attribute and `CacheableTrait`:

```php
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Attribute\Cacheable;
use Gacela\Framework\Attribute\CacheableTrait;

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

Storage is `InMemoryCacheStorage` by default, which means entries die with the request on PHP-FPM. For cross-request caching swap in a shared backend (APCu, Redis, PSR-16) via `CacheableConfig::setStorage()`.

Clear selectively:

```php
CatalogFacade::clearMethodCache();                        // all of this facade
CatalogFacade::clearMethodCacheFor('getPopularProducts'); // one method, any args
```

Full reference: [Cacheable methods](cacheable-methods.md).

## Layer 3 — Value primitives

When *your code* needs a cache — compiled artefacts, parsed data, a build pipeline — use `Gacela\Framework\Cache\FileCache`:

```php
use Gacela\Framework\Cache\FileCache;

$cache = new FileCache('/var/cache/myapp');

$cache->put('user:42', $user, ttl: 600);
$cache->get('user:42');     // $user, or null after TTL expiry
$cache->forget('user:42');
$cache->clear();
```

- One `.php` file per key (SHA1-hashed), written atomically via staged `.tmp` + `rename`.
- TTL per entry; `ttl: 0` means forever.
- `beginBatch()` / `commitBatch()` defer writes behind a single index-locked flush — useful for warming many entries at once.
- `stats()` returns entry count, total bytes, and oldest/newest timestamps.
- Safe against torn reads: concurrent readers see either the previous file or the new one, never a half-written one.

### ScopedCache — dependency-aware decorator

When invalidating one entry should cascade to every downstream entry that derived from it, wrap `FileCache` in `ScopedCache`:

```php
use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\ScopedCache;

$cache = new ScopedCache(new FileCache('/var/cache/myapp'));

$cache->put('ns:core', $envCore);
$cache->put('file:a.php', $compiledA);
$cache->put('fragment:a#1', $fragment);

$cache->dependsOn('file:a.php', 'ns:core');
$cache->dependsOn('fragment:a#1', 'file:a.php');

$cache->invalidate('ns:core');          // cascades: file:a.php and fragment:a#1 also go
$cache->invalidateLeaf('file:a.php');   // only this key; dependents stay valid
```

- `get` / `put` / `has` delegate straight to the underlying `FileCache` — zero overhead on the hot path.
- The dependency graph is persisted alongside the values (`.gacela-scoped-cache-graph.php`) and survives process restarts.
- Cycles are rejected eagerly at `dependsOn()` — self, two-node, and transitive.
- Single-writer concurrency: multiple processes racing on `dependsOn()` may lose edges added between load and persist. The value store underneath remains read-safe under concurrency regardless.

## Picking a layer

- Make Gacela's own resolution faster → Layer 1, `enableFileCache()`.
- Memoise a specific facade method → Layer 2, `#[Cacheable]`.
- Cache arbitrary application data → Layer 3, `FileCache`.
- Same, but invalidation must cascade → Layer 3, `ScopedCache`.
