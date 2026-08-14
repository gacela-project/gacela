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
- **On-disk**: `ClassNamePhpCache` persists resolved class names to `gacela-class-names-{appRootHash}-{bootstrapHash}.php` — the first hash keeps two apps sharing one cache dir apart, the second keeps two bootstraps of one app apart (different `projectNamespaces` or suffix types resolve differently, so each bootstrap reads and writes its own file). `CustomServicesPhpCache` persists to `gacela-custom-services-{appRootHash}.php` with no bootstrap hash: its entries derive from the caller's source alone. `MergedConfigCache` persists the merged configuration to `gacela-merged-config-{appRootHash}[-{env}].php`.

Finding the class is half the work; building it is the other half. Every module gets its own container, and without help each would reflect the classes they have in common — so they all share one constructor-plan cache, which is what keeps a tenth module's container from re-reflecting what the first nine already described. It lives for the process and costs nothing to have.

There is no on-disk equivalent switched on for you: persisting those plans was measured for 2.0 and the file costs more to load than the reflection it saves. The container's own `writeCompiledFactories()` / `useCompiledFactories()` are forwarded for an application that has measured its own case — see [the numbers](container-configuration.md#underlying-container-features-gacela-does-not-expose).

Configure at bootstrap:

```php
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;

Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
    $config->enableFileCache();                  // use the default cache dir
    // $config->enableFileCache('var/cache');    // or pick one, relative to the app root
    // $config->setFileCache(false);             // explicitly off
    // $config->resetInMemoryCache();            // wipe static caches (tests)
});
```

A path given here is resolved **relative to the app root**, and a leading `/` does not escape it: `enableFileCache('/var/cache/gacela')` writes to `{appRoot}/var/cache/gacela`. To point the cache at a genuinely absolute path outside the project, use the `GACELA_CACHE_DIR` environment variable, which is read first and taken verbatim — also the handy way to reuse one image across environments.

**One exception, on Windows**: a drive-letter path (`C:\cache`, `C:/cache`) is taken as given and does escape the app root, because rebasing it would produce `{appRoot}\C:\cache`, which names nothing. A path already inside the app root is likewise left alone on either platform. So `GACELA_CACHE_DIR` is the portable way to ask for a directory outside the project — the only spelling that means the same thing on both.

Typical wiring:

- **Development** — file cache **off**. Edits take effect immediately.
- **Production** — file cache **on**, directory baked into the image. Re-deploy to refresh.
- **Tests** — call `resetInMemoryCache()` between suites so resolution state doesn't bleed.

See also: [Opcache preload](opcache-preload.md) for getting PHP itself to cache Gacela's own source files, and [Production performance](production-performance.md) for the full production tuning checklist.

## Layer 2 — Cacheable facade methods

Cache the *result* of a facade method with the `#[Cacheable]` attribute and `$this->cached()`. Storage is `InMemoryCacheStorage` by default, which means entries die with the request on PHP-FPM; for cross-request caching swap in a shared backend (APCu, Redis, PSR-16) via `CacheableConfig::setStorage()`.

Full reference, including keys, invalidation, TTL overrides, and the storage contract: [Cacheable methods](cacheable-methods.md).

## Layer 3 — Value primitives

When *your code* needs a cache — compiled artefacts, parsed data, a build pipeline — use `Gacela\Framework\Cache\FileCache`: one atomically written file per key, per-entry TTLs, batched writes, and stats. When invalidating one entry should cascade to every entry derived from it, wrap it in `ScopedCache`, its dependency-aware decorator.

Full reference: [FileCache and ScopedCache](file-cache.md).

## Picking a layer

- Make Gacela's own resolution faster → Layer 1, `enableFileCache()`.
- Memoise a specific facade method → Layer 2, [`#[Cacheable]`](cacheable-methods.md).
- Cache arbitrary application data → Layer 3, [`FileCache`](file-cache.md).
- Same, but invalidation must cascade → Layer 3, [`ScopedCache`](file-cache.md#scopedcache--dependency-aware-decorator).
