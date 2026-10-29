# Benchmarks

This suite is a **blocking CI gate** (`.github/workflows/phpbench.yml`): every PR benchmarks its base branch, then its head, and fails when a gated subject regresses by more than the `phpbench.json` assertion (`mode(variant.time.avg) <= mode(baseline.time.avg) +/- 10%`). Because the gate blocks merges, every bench must be **stable** (low `rstdev`) and **intentional** (measures one documented path).

## Groups

Every subject must carry a `#[Groups([...])]` with exactly one **gating** group and one domain group:

| Group | Kind | Meaning |
|---|---|---|
| `gate` | gating | Stable macro bench. Asserted by CI with the strict ±10% rule. |
| `informational` | gating | Measured and reported by CI, never blocks. Carries a widened class-level `#[Assert]` so full local runs can't false-fail either. |
| `micro` | marker | Sub-microsecond subject — the most common *reason* to be informational. Also selects the subset locally. |
| `bootstrap` \| `resolve` \| `config` \| `container` \| `cache` \| `cacheable` | domain | For running subsets locally. |

CI runs `phpbench run --ref=base_gate --group=gate` as the blocking step and `--group=informational` as a `continue-on-error` step.

A subject with no gating group runs in **neither** CI step and is silently unmeasured — `CacheableBench` sat in that hole until it was given `informational`.

Both sides of the comparison must run **the same group**. The baseline stores one tag per group (`base_gate`, `base_info`) precisely so a head run is never compared against a base run that did a different amount of work in a different order; that mismatch alone moved a disk-bound subject by ~50%.

### Introducing cache-read security checks

The comparison helper (`.github/phpbench/compare.py`) makes one bounded exception when the base has no `FileCache::isSafeToRead` and the head introduces that method with an early rejection in `FileCache::readArray`. Only these four subjects receive a 40% ceiling for that comparison:

- `BootstrapBench::bench_bootstrap_warm`
- `BootstrapBench::bench_bootstrap_auto_warmed`
- `ConfigInitBench::bench_config_init_warm`
- `ConfigInitBench::bench_config_init_auto_warmed`

Successful per-read security checks add filesystem metadata and ownership checks. Interleaved Linux measurements found about 1.3 microseconds of added work per warm configuration read, around 30% on that short path. CI measured +32.57% for warm configuration reads, +18.38% for automatically warmed reads, +11.22% for warm bootstrap and +9.90% for automatically warmed bootstrap. The 40% ceiling covers the measured security cost plus the existing 10% margin. This accepts that measured cost without removing the checks.

During that transition, CI runs the other gated subjects first (`base_gate_core`, 10%), then those four exact subjects (`base_gate_startup`, 40%). Both commits run identical selections in that order. PhpBench's `--assert` replaces the configured assertion for the selected subjects; the aggregate report still shows their actual times and differences. Once the base contains the guard, every gated subject automatically returns to 10%, including those four. Missing commits or unreadable source stop the comparison. A new method without its read-time rejection receives no allowance.

The helper and its plan are copied into the runner's temporary directory before checking out the base, which may predate the helper. Immutable base and head commit IDs are captured in that plan, and each run verifies its checkout. Focused tests include PhpBench's debug executor to select the actual subject inventory and check assertion replacement with fixed test inputs; these test values are stored only in a temporary directory and are never used by the measured CI runs.

```bash
python3 -B -m unittest discover -s .github/phpbench -p 'test_*.py'
```

Rule of thumb: if the subject's average time is below ~0.2μs, it belongs in `informational` and is tagged `micro` — at that scale timer quantization alone moves the mode by ~10%, which would false-fail the gate on unrelated PRs. Subjects in the 0.2–1μs range can stay gated when their `rstdev` holds below ~3% (e.g. the warm-resolve benches).

## Retries: why the informational benches loosen the threshold

`phpbench.json` sets `"runner.retry_threshold": 5` — phpbench re-runs an iteration set whenever its `rstdev` exceeds that, and **the retry is unbounded**: `SubjectMetadata::setRetryLimit()` exists in phpbench 1.7 but nothing ever calls it, so there is no cap and no config key for one. On a quiet machine that is invisible. On a loaded CI runner it is not.

Measured here, same bench, same commit, one machine, varying only the threshold:

| `--retry-threshold` | `ScopedCacheBench` |
|---|---|
| 0.5 | 256 s |
| 5 (the global default) | 34 s |
| 10 | 6.3 s |
| 20 | 6.5 s |

A 38× spread produced by nothing but the retry threshold. That is what made the CI guard's baseline step take 11–12 minutes and get killed by the job timeout while the gate step beside it passed in 15 seconds — a red check that was never a regression.

So every `informational` class carries `#[RetryThreshold(20)]`. The gate group keeps the global 5, because a ±10% assertion is only meaningful when the reading under it is stable. The informational numbers are **reported and never asserted**, so buying a tighter `rstdev` for them with unbounded retries is cost without benefit — and the `rstdev` column is printed beside every number, so a reader can still see when one is noisy.

20 rather than 10: both sit past the knee of the curve above, and CI runners are noisier than the machine those numbers came from.

**`rstdev` is not sufficient on its own.** It measures spread *within* one run, but the gate compares two runs made at different moments. An I/O-bound subject can hold `rstdev` under 2% and still drift well past 10% run-to-run. Before gating a subject that touches the disk, network, or clock, run it twice against itself:

```bash
vendor/bin/phpbench run <path> --tag=selfbase --store --progress=none
vendor/bin/phpbench run <path> --ref=selfbase --report=aggregate --progress=none
```

**Store the baseline twice before believing it.** The stored run is one sample too, and an unusually fast one makes every later run look like a regression. Measured here: against one baseline, `bench_resolve_with_inject_attribute` reported `+10.38%` and then `+10.66%` on untouched code -- enough to condemn the subject -- and against a baseline stored minutes later, `-0.04%` and `-5.08%`. The tell was that *every* subject in the file had moved the same direction, which says something about the baseline rather than about any one of them.

```bash
vendor/bin/phpbench run <path> --tag=selfbase2 --store --progress=none
vendor/bin/phpbench run <path> --ref=selfbase2 --report=aggregate --progress=none
```

If unchanged code drifts near the threshold **against both baselines**, the subject belongs in `informational`, not in `gate` with a widened tolerance. `ScopedCacheBench` is the worked example: gated at ±10%, widened to ±30%, and it still produced +48.96% on a PR that changed no runtime code. Widening again would have left a "gate" that only catches a >1.6x regression. A gate that fails on PRs which never touched the code does not get fixed — it gets bypassed, which costs more than not having it.

Prefer moving the subject to `informational` over widening a gate past ~30%.

## Sampling and warmup

Defaults come from `phpbench.json`: **200 revs, 10 iterations, warmup 2**. Inheriting them is fine and preferred; annotate only to deviate:

- `micro` subjects: at least `#[Revs(1000)]`, `#[Iterations(5)]`.
- Bootstrap-heavy subjects (each rev runs `Gacela::bootstrap()` or module loading): lower revs are fine (`#[Revs(20..50)]`, `#[Iterations(5)]`) to keep the suite fast; never below 5 iterations.

After changing a bench, check its `rstdev` in the aggregate report: gated subjects should stay below ~3%.

## Purity

A subject body must contain only the path it documents. Hoist incidental allocation and setup into `#[BeforeMethods]` / `#[BeforeClassMethods]` (see `ClassInfoBench`). When an allocation *is* the measured path (e.g. `ContainerResolutionBench` constructs a fresh container to measure cold resolution), say so in the class docblock.

## State isolation

Benches share one process per subject, and the repository shares one machine:

- Reset global state in setup (`resetInMemoryCache()`, `Config::resetInstance()`, `ServiceResolverCache::resetCache()`, ...) so subjects don't leak into each other.
- **Never enable the file cache without an explicit directory.** With no directory the cache uses `{appRoot}/.gacela/cache`, which can retain entries from another benchmark run. Use `enableFileCache(__DIR__ . '/.gacela/cache')` and wipe it in `#[BeforeClassMethods]` / `#[AfterClassMethods]` (see `FileCacheBench`).

## Adding a bench

1. One class per measured concern, `*Bench.php`, under the matching domain dir.
2. Class docblock: what path it measures and why it matters.
3. `#[Groups([...])]`: exactly one of `gate` / `informational`, plus a domain group (and `micro` too when the subject is sub-microsecond).
4. Explicit revs/iterations only when deviating from the defaults (per above).
5. `informational` subjects additionally get the widened class-level assert: `#[Assert('mode(variant.time.avg) <= mode(baseline.time.avg) +/- 1000%')]`.
6. Run locally before pushing:
   ```bash
   composer phpbench                                       # full suite
   vendor/bin/phpbench run --group=gate --report=aggregate
   # simulate the CI guard -- same group on both sides
   vendor/bin/phpbench run --tag=base_gate --store --group=gate
   vendor/bin/phpbench run --ref=base_gate --group=gate
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
| `FileCache/BatchWriteBench` | gate, cache | 200 cache puts batched vs unbatched |
| `Cache/ScopedCacheBench` | informational, cache | ScopedCache put/get + dependsOn cycle detection (disk-bound — see above) |
| `Attribute/CacheableBench` | informational, cacheable | `#[Cacheable]` backtrace inference vs explicit args |
| `ClassResolver/ClassInfo/ClassInfoBench` | informational, micro, resolve | `ClassInfo::from()` for anonymous vs real classes |
| `Config/ConfigTypedAccessBench` | informational, micro, config | typed config getters vs raw `get()`+cast witness |
