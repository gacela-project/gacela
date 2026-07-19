# Benchmarks

This suite is a **blocking CI gate** (`.github/workflows/phpbench.yml`): every PR
benchmarks its base branch, then its head, and fails when a gated subject
regresses by more than the `phpbench.json` assertion
(`mode(variant.time.avg) <= mode(baseline.time.avg) +/- 10%`). Because the gate
blocks merges, every bench must be **stable** (low `rstdev`) and **intentional**
(measures one documented path).

## Groups

Every subject must carry a `#[Groups([...])]` with exactly one gating group and
one domain group:

| Group | Meaning |
|---|---|
| `gate` | Stable macro bench. Asserted by CI with the strict ±10% rule. |
| `micro` | Sub-microsecond subject. Reported by CI (non-blocking); carries a widened class-level `#[Assert]` so full local runs can't false-fail either. |
| `bootstrap` \| `resolve` \| `config` \| `container` \| `cache` | Domain, for running subsets locally. |

CI runs `phpbench run --ref=base --group=gate` as the blocking step and
`--group=micro` as a `continue-on-error` informational step.

Rule of thumb: if the subject's average time is below ~0.2μs, it belongs in
`micro` — at that scale timer quantization alone moves the mode by ~10%, which
would false-fail the gate on unrelated PRs. Subjects in the 0.2–1μs range can
stay gated when their `rstdev` holds below ~3% (e.g. the warm-resolve benches).

## Sampling and warmup

Defaults come from `phpbench.json`: **200 revs, 10 iterations, warmup 2**.
Inheriting them is fine and preferred; annotate only to deviate:

- `micro` subjects: at least `#[Revs(1000)]`, `#[Iterations(5)]`.
- Bootstrap-heavy subjects (each rev runs `Gacela::bootstrap()` or module
  loading): lower revs are fine (`#[Revs(20..50)]`, `#[Iterations(5)]`) to keep
  the suite fast; never below 5 iterations.

After changing a bench, check its `rstdev` in the aggregate report: gated
subjects should stay below ~3%.

## Purity

A subject body must contain only the path it documents. Hoist incidental
allocation and setup into `#[BeforeMethods]` / `#[BeforeClassMethods]`
(see `ClassInfoBench`). When an allocation *is* the measured path (e.g.
`ContainerResolutionBench` constructs a fresh container to measure cold
resolution), say so in the class docblock.

## State isolation

Benches share one process per subject, and the repository shares one machine:

- Reset global state in setup (`resetInMemoryCache()`, `Config::resetInstance()`,
  `DocBlockResolverCache::resetCache()`, ...) so subjects don't leak into each
  other.
- **Never enable the file cache without an explicit directory.** With no
  directory the cache falls back to `sys_get_temp_dir()`, which is shared with
  every other test run on the machine — stale `gacela-merged-config.php` files
  from unrelated runs will poison the bench. Use
  `enableFileCache(__DIR__ . '/.gacela/cache')` and wipe it in
  `#[BeforeClassMethods]` / `#[AfterClassMethods]` (see `FileCacheBench`).

## Adding a bench

1. One class per measured concern, `*Bench.php`, under the matching domain dir.
2. Class docblock: what path it measures and why it matters.
3. `#[Groups([...])]`: `gate` or `micro`, plus a domain group.
4. Explicit revs/iterations only when deviating from the defaults (per above).
5. `micro` subjects additionally get the widened class-level assert:
   `#[Assert('mode(variant.time.avg) <= mode(baseline.time.avg) +/- 1000%')]`.
6. Run locally before pushing:
   ```bash
   composer phpbench                                  # full suite
   vendor/bin/phpbench run --group=gate --report=aggregate
   vendor/bin/phpbench run --tag=base --store         # simulate the CI guard
   vendor/bin/phpbench run --ref=base --group=gate
   ```

## Current inventory

| Bench | Groups | Measures |
|---|---|---|
| `Bootstrap/BootstrapBench` | gate, bootstrap | `Gacela::bootstrap()` cold vs merged-config-cache warm |
| `FileCache/FileCacheBench` | gate, bootstrap | bootstrap + 7-module load, file cache off vs on |
| `GacelaGlobalBench` | gate, resolve | warm facade access via `Gacela::addGlobal()` anonymous classes |
| `ModuleExample/ModuleExampleBench` | gate, resolve | warm facade access via a conventional on-disk module |
| `ClassResolver/ResolverCacheHitBench` | gate, resolve | single `doResolve()` cache hit — the hottest steady-state path |
| `ClassResolver/EventDispatchBench` | gate, resolve | cache-hit resolve with events off / unrelated listener / generic listener |
| `ServiceResolver/DocBlockResolverBench` | gate, resolve | attribute vs phpdoc service resolution, single + cached-repeated |
| `Container/ContainerResolutionBench` | gate, container | cold container resolution: no-deps / `#[Inject]` / bindings / deep chain |
| `Config/ConfigInitBench` | gate, config | `Config::init()` cold vs merged-config-cache load |
| `Cache/ScopedCacheBench` | gate, cache | ScopedCache put/get + dependsOn cycle detection |
| `FileCache/BatchWriteBench` | gate, cache | 200 cache puts batched vs unbatched |
| `ClassResolver/ClassInfo/ClassInfoBench` | micro, resolve | `ClassInfo::from()` for anonymous vs real classes |
| `Config/ConfigTypedAccessBench` | micro, config | typed config getters vs raw `get()`+cast witness |
