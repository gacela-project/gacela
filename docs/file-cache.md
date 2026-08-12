# FileCache and ScopedCache

When *your code* needs a cache — compiled artefacts, parsed data, a build pipeline — use `Gacela\Framework\Cache\FileCache`. It is the value layer of [Gacela's caching](caching.md): the framework does not put anything in it on its own; your application decides the keys, the values, and the lifetimes.

## FileCache

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

## ScopedCache — dependency-aware decorator

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

## See also

- [Caching](caching.md) — the three caching layers and how to pick one
- [Cacheable methods](cacheable-methods.md) — caching facade method results instead of raw values
