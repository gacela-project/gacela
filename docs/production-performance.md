# Production Performance

Gacela ships every lever it needs to run fast, but the defaults favour a smooth development loop — most notably the file cache is **off by default** so edits take effect immediately. In production you flip those switches on. This page is the full checklist in one place; each step links to its detailed reference.

> **TL;DR** — enable the file cache, warm it at deploy, preload the framework into
> opcache, optimise the autoloader, and give `#[Cacheable]` a cross-request store.

## 1. Enable the file cache

The single highest-impact switch. It persists resolved class names, custom services, and the merged configuration to disk, turning per-boot namespace walks and config globbing into a single `require`.

```php
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;

Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
    $config->enableFileCache('var/cache/gacela'); // default is OFF; relative to the app root
    // Do NOT call resetInMemoryCache() in production — that is for tests.
});
```

The path is resolved **relative to the app root**, and a leading `/` does not escape it — for a directory outside the project use `GACELA_CACHE_DIR` (step 7). Keep the cache **off** in development so changes are picked up without clearing anything. See [Caching → Layer 1](caching.md#layer-1--framework-resolution-cache).

## 2. Warm the cache at deploy

Populate the on-disk caches ahead of the first request so no user pays the cold resolution cost:

```bash
vendor/bin/gacela cache:warm      # add --attributes to pre-scan #[ServiceMap]
vendor/bin/gacela cache:clear     # drop the caches (run before re-warming)
```

Run `cache:warm` as a deploy step, after `composer install` and before traffic is routed to the new release.

`cache:warm` warms *one bootstrap*: the class-name cache file is keyed by the bootstrap's `projectNamespaces` and suffix types, so a deploy with several entrypoints that bootstrap differently runs it once per entrypoint — each writes its own file, and none answers for another.

## 3. Preload the framework into opcache

Point `opcache.preload` at `resources/gacela-preload.php` to load Gacela's core files into shared memory at PHP startup, removing their compilation cost from every request.

Preloaded files are snapshotted at startup, so **restart PHP-FPM after every deploy**. The `php.ini` block, Docker recipe, `GACELA_PRELOAD_USER_FILES` and troubleshooting are in [Opcache preload](opcache-preload.md).

## 4. Optimise the autoloader

```bash
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

`--classmap-authoritative` skips filesystem probing for every class — safe once all production classes are in the classmap (they are, with the flag above).

## 5. Disable unused event listeners

Framework lifecycle events are zero-cost when nothing listens, but if you register no listeners in production you can skip the dispatch machinery entirely:

```php
Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
    $config->enableFileCache('/var/cache/gacela');
    $config->disableEventListeners();
});
```

See [Events](events.md).

## 6. Give `#[Cacheable]` a cross-request store

Only if you use `#[Cacheable]`. The default storage dies with the PHP-FPM request, so entries never survive to the next one — wire a shared backend (APCu, Redis, any PSR-16) via `CacheableConfig::setStorage()` at bootstrap. Setup and the caveats are in [Cacheable methods](cacheable-methods.md#pluggable-storage-backend).

## 7. Externalise the cache directory (optional)

When one built image serves multiple environments, point the cache dir at a writable path per environment instead of baking it into the bootstrap:

```bash
GACELA_CACHE_DIR=/var/cache/gacela-prod
```

`GACELA_CACHE_DIR` overrides the directory at runtime and takes precedence over the bootstrap value.

## Checklist

| Step | Lever | Reference |
|---|---|---|
| 1 | `enableFileCache('/var/cache/gacela')` | [Caching](caching.md) |
| 2 | `vendor/bin/gacela cache:warm` at deploy | [Caching](caching.md) |
| 3 | `opcache.preload` + FPM restart on deploy | [Opcache preload](opcache-preload.md) |
| 4 | `composer install --no-dev --optimize-autoloader --classmap-authoritative` | — |
| 5 | `disableEventListeners()` if unused | [Events](events.md) |
| 6 | `CacheableConfig::setStorage()` (APCu/Redis) | [Cacheable methods](cacheable-methods.md) |
| 7 | `GACELA_CACHE_DIR` per environment | [Caching](caching.md#layer-1--framework-resolution-cache) |
