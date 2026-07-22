# Production Performance

Gacela ships every lever it needs to run fast, but the defaults favour a smooth
development loop — most notably the file cache is **off by default** so edits take
effect immediately. In production you flip those switches on. This page is the
full checklist in one place; each step links to its detailed reference.

> **TL;DR** — enable the file cache, warm it at deploy, preload the framework into
> opcache, optimise the autoloader, and give `#[Cacheable]` a cross-request store.

## 1. Enable the file cache

The single highest-impact switch. It persists resolved class names, custom
services, and the merged configuration to disk, turning per-boot namespace walks
and config globbing into a single `require`.

```php
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;

Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
    $config->enableFileCache('/var/cache/gacela'); // default is OFF
    // Do NOT call resetInMemoryCache() in production — that is for tests.
});
```

Keep it **off** in development so changes are picked up without clearing anything.
See [Caching → Layer 1](caching.md#layer-1--framework-resolution-cache).

## 2. Warm the cache at deploy

Populate the on-disk caches ahead of the first request so no user pays the cold
resolution cost:

```bash
vendor/bin/gacela cache:warm      # add --attributes to pre-scan #[ServiceMap]
vendor/bin/gacela cache:clear     # drop the caches (run before re-warming)
```

Run `cache:warm` as a deploy step, after `composer install` and before traffic
is routed to the new release.

## 3. Preload the framework into opcache

Loads Gacela's core files into shared memory at PHP startup — typically a 20–30%
throughput gain and lower per-request memory.

```ini
; php.ini or FPM pool
opcache.enable=1
opcache.preload=/path/vendor/gacela-project/gacela/resources/gacela-preload.php
opcache.preload_user=www-data
```

Preloaded files are snapshotted at startup, so **restart PHP-FPM after every
deploy**. Full setup, Docker recipe, and troubleshooting live in
[Opcache preload](opcache-preload.md).

## 4. Optimise the autoloader

```bash
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

`--classmap-authoritative` skips filesystem probing for every class — safe once
all production classes are in the classmap (they are, with the flag above).

## 5. Disable unused event listeners

Framework lifecycle events are zero-cost when nothing listens, but if you register
no listeners in production you can skip the dispatch machinery entirely:

```php
Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
    $config->enableFileCache('/var/cache/gacela');
    $config->disableEventListeners();
});
```

See [Events](events.md).

## 6. Give `#[Cacheable]` a cross-request store

The default `InMemoryCacheStorage` dies with the PHP-FPM request, so cached method
results never survive to the next request. For real cross-request caching, wire a
shared backend (APCu, Redis, any PSR-16) once at bootstrap:

```php
use Gacela\Framework\Attribute\CacheableConfig;

CacheableConfig::setStorage($myPsr16BackedStorage);
```

See [Cacheable methods](cacheable-methods.md).

## 7. Externalise the cache directory (optional)

When one built image serves multiple environments, point the cache dir at a
writable path per environment instead of baking it into the bootstrap:

```bash
GACELA_CACHE_DIR=/var/cache/gacela-prod
```

`GACELA_CACHE_DIR` overrides the directory at runtime and takes precedence over
the bootstrap value.

## Checklist

| Step | Lever | Reference |
|---|---|---|
| 1 | `enableFileCache('/var/cache/gacela')` | [Caching](caching.md) |
| 2 | `vendor/bin/gacela cache:warm` at deploy | — |
| 3 | `opcache.preload` + FPM restart on deploy | [Opcache preload](opcache-preload.md) |
| 4 | `composer install --no-dev --optimize-autoloader --classmap-authoritative` | — |
| 5 | `disableEventListeners()` if unused | [Events](events.md) |
| 6 | `CacheableConfig::setStorage()` (APCu/Redis) | [Cacheable methods](cacheable-methods.md) |
| 7 | `GACELA_CACHE_DIR` per environment | — |
