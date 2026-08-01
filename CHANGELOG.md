# Changelog

## Unreleased

A foundation major. The runtime change is two lines — PHP `>=8.3` and
`gacela-project/container` `^2.0.0`, up from a `0.x`. Most of what follows comes
from the second: `#[Lazy]`, `#[Inject]` on properties, PSR-11-correct `has()`,
and container exceptions where 0.x emitted raw PHP errors.

It is **not** the "one container" release; that moved to 2.1 (#539). No longer
for want of the primitive — container 1.3 shipped `createScope()` and closed
[container#106](https://github.com/gacela-project/container/issues/106) — but
because the consolidation only stays reviewable if it ships alone.

It was not meant to be a performance release either, and mostly is not: the
three perf spikes measured sub-millisecond and were closed rather than shipped,
and writing compiled plans to disk measured a **net loss**. One win survived a
benchmark — every container now shares one constructor-plan cache.

Migration is three mechanical renames. See [UPGRADE.md](UPGRADE.md), and run
`vendor/bin/gacela doctor` on 1.21 first — one of the three fails silently.

### Added

- **`GacelaConfig::loadDefinitions()`** registers a whole definition set from an
  array, or a `.php`/`.json` file, for wiring that is generated, shared between
  environments, or reviewed as a diff:
  ```php
  $config->loadDefinitions([
      LoggerInterface::class => FileLogger::class,
      Database::class => ['singleton' => DatabasePool::class],
      'db.dsn' => ['value' => 'pgsql://localhost/app'],
  ]);
  $config->loadDefinitions(__DIR__ . '/config/services.json');
  ```
  App-wide, like `addBinding()`; `Container::load()`/`loadFile()` are forwarded
  so a Provider can scope definitions to one module. Sources apply in order and
  **after** the imperative registrations, so a file overrides `addBinding()` —
  which is what an override file is for. Tags accumulate instead. Paths are used
  as given: write them with `__DIR__`. No YAML — pass `Yaml::parseFile(...)`.

- **`GacelaConfig::afterResolving()`** runs a callback on an instance after the
  container builds it:
  ```php
  $config->afterResolving(
      LoggerAwareInterface::class,
      static fn (LoggerAwareInterface $s) => $s->setLogger($logger),
  );
  ```
  The id may name an **interface**, so one registration covers every
  implementation. Fires on `get()`, `getOrFail()` and `make()` in registration
  order, costs nothing when unused, and does not fire for a nested constructor
  dependency. A callback that throws evicts the instance.

- **`GacelaConfig::tag()`** groups services under a label, reaching every
  module's container:
  ```php
  $config->tag([NotEmptyValidator::class, EmailValidator::class], 'validators');
  ```
  A module adding to a tag in its own Provider stays local to that module. Use
  `tag()` for an unkeyed set you iterate, `addHandlerRegistry()` for a keyed
  lookup that throws on a miss.

- **The dependency tree is an actual tree**, taken from the container so
  bindings and contextual bindings are already applied:
  ```
  ├── ✓ $cacheWarmService: …\CacheWarmService (autowired)
  └── ✓ $formatter: …\CacheWarmOutputFormatter (autowired)
      └── ✗ $output: Symfony\…\OutputInterface (unresolvable)
  ```
  A missing dependency now says *whose* it is. A cycle is marked `(cycle)` and
  cut. Counts are of distinct classes, so one pulled in by three parents counts
  once and is drawn three times.

  All three commands that report it draw the same tree — `debug:dependencies
  --tree`, `debug:module` and `debug:container`. The latter two printed a flat
  list under a heading that said "tree", `debug:container` numbering it `1. 2.
  3.`, and each rendered it its own way.

- **`cache:clear` also clears the container's in-process memos** — reflection
  output held in statics that outlive every container and that no file holds.

- **A Psalm plugin typing the pillar accessors from `#[ServiceMap]`**, the
  counterpart of the PHPStan extension. Before, `psalm-gacela.xml` only
  *suppressed* the error, so the accessor evaluated to `mixed`:
  ```xml
  <plugins>
      <pluginClass class="Gacela\Psalm\Plugin"/>
  </plugins>
  ```
- **One constructor-plan cache for every container** (container 1.4's
  `PlanCache`). Gacela's containers are sibling roots, not a tree, and they are
  configured from the same `gacela.php`, so each used to reflect the same
  classes again. Ten containers resolving one four-level chain drop **~36%** in
  time, measured on CI at 90.3μs → 58.0μs. Only reflection output is shared:
  bindings, aliases, tags, singletons and stored instances stay private to each
  container. Pass your own `PlanCache` as the container's fourth argument to opt
  one out.
- **`Container::lazy()`, `writeCompiledFactories()`, `useCompiledFactories()`
  and `compileReport()`** are forwarded, and `writeCompiledCache()` gained
  container 1.4's build-stamp argument. Nothing in Gacela calls the compilation
  methods — writing plans to disk was measured a net loss — but they are
  reachable for an application that has measured its own case.

- **`Gacela\Framework\Attribute\Inject`** — application code no longer imports
  a vendor namespace for the one attribute-first surface that required it. It
  subclasses the container's attribute, and since attributes are read with
  `ReflectionAttribute::IS_INSTANCEOF` both imports work side by side.

  RFC-0001 planned this as a `class_alias()` and withdrew it the same day: an
  exact-FQN read follows neither an alias nor a subclass, and the failure is
  **silent** — the parameter simply never injected. Subclassing is supported
  because the container now reads for it.
- **`#[Inject]` targets properties and setters**, not only constructor
  parameters, for classes whose constructor is not yours to change.
- `#[Lazy]` joins `#[Inject]`, `#[Singleton]` and `#[Factory]` as an attribute
  honoured by `AbstractFactory::make()`.
- `Container::provides()`, `taggedByKey()` and `taggedKeys()` are forwarded.

### Changed

- **`gacela-project/container` `^2.0.0`** (was `^0.10.0`). `Container` is
  `final`, so Gacela decorates it by composition. Two of its fixes land in
  Gacela's own path: a class-string sharing a name with a function was *invoked*
  instead of instantiated, and `has()` remembered a negative, so a class
  declared after the first probe stayed invisible.

  2.0 puts the whole surface on `ContainerInterface`, so an unforwarded method
  is now a compile error rather than something silently unreachable. Two more
  of its additions replace code here outright:

  - **`withSelfReference()` removes the closure-wrapping layer.** Because the
    inner container passed *itself* to service closures, every user closure was
    re-wrapped so `static fn (Container $c) => $c->getLocator()` kept working —
    and because `factory()` and `protect()` mark closures by identity, each
    wrapper had to be tracked in a `WeakMap` so `set()` would not wrap it twice
    and drop the mark. That is roughly 50 lines and 29 touchpoints, replaced by
    telling the container which object to hand out. The `WeakMap` goes with it,
    and so does the leak it was fixed for.
  - **`createScope()` is forwarded**, which was impossible while a scope could
    not be decorated: the raw child would have handed its closures the inner
    container. A scope is now a decorator like its parent, so it keeps the
    Locator and the lifecycle events.

  One caveat, measured and reported rather than absorbed quietly: container
  2.0 resolves 11-28% slower **cold** than 1.5.0 —
  [container#181](https://github.com/gacela-project/container/issues/181),
  root-caused to the per-class argument builder being composed on a class's
  first resolution, so a container that builds a class once pays for a shortcut
  it never takes. Gacela's own benchmarks are unaffected: bootstrap, config
  init, class resolution, the resolver cache, event dispatch and the file
  caches all moved within ±8% and bootstrap came out ~3% faster. Only the
  benchmarks that construct raw containers in a loop show it, and those are
  reported rather than gating until it is fixed.

  `load()`/`loadFile()` return the ids they registered and take an optional
  per-id callback, which closes a gap `loadDefinitions()` shipped with:
  definitions now emit `BindingRegisteredEvent` like every other registration,
  where naming them previously meant reconstructing the ids and missing aliases. `ContainerStats::memoryUsageFormatted()` is
  `processMemoryFormatted()` — `debug:container` labels it **Process Memory**,
  which is what it always measured.
- **PHP floor raised to `>=8.3`** (was `>=8.1`). 8.1 is end of life and 8.2's
  security window closes in December 2026.
- **`symfony/*` widened to `^7.0 || ^8.0`** (was `^6.4`). Gacela no longer
  decides a consumer's Symfony major.
- **`ConsoleFacade::getContainerStats()` and `ConsoleFactory::getContainerStats()`
  return `ContainerStats`** instead of an array.
- **The PHPStan suppression for undeclared pillar accessors is gone.** An
  accessor you have not declared is reported rather than silently typed `mixed`.
- Class constants on `AbstractSetupGacela` and `ConfigInterface` declare types,
  so a subclass redeclaring one is checked at compile time.

### Fixed

- **The container retained every closure it was ever handed.** The mark that
  stops a wrapper being wrapped twice held its keys strongly and was never
  cleaned, so `set()`, `bind()`, `extend()`, `factory()` and `protect()` leaked
  their closures and everything each captured. Overwriting one id 5000 times
  held 6.1 MB for a single live binding; it is 1.8 KB now. Bounded in a normal
  bootstrap, but it bit anything re-registering on a long-lived container.
- **`CacheWarmedEvent` reported skipped modules as failed**, so a listener
  alerting on `failedCount() > 0` fired on a successful deploy. A missing pillar
  class and one whose autoloading threw now count separately, and
  `skippedCount()` reports the healthy one. `cache:warm` gains a
  `Classes failed:` line.
- **`make:module` and `make:file` failed on a brand-new project** —
  `FileContentIo::mkdir()` omitted the recursive flag.
- **`Container` emitted PHP 8.5 deprecation notices on a core path.**
- **The in-memory copy of the file-backed caches survived `Gacela::resetCache()`**,
  so entries read from disk kept answering after a reset.
- **`extendService()` on an id naming an autowirable class threw** instead of
  scheduling the extension.
- `Gacela::resetCache()` drops the memoized "this class does not exist" answers.

### Deprecated

- Resolving a pillar from a `@method` docblock, or by scanning the caller's
  `use` statements, raises `E_USER_DEPRECATED` and is removed in 3.0. Declare it
  with `#[ServiceMap]`.

### Removed (BREAKING)

- `AbstractDependencyProvider` — extend `AbstractProvider` instead.
- `GacelaConfig::addMappingInterface()` — use `addBinding()`.
- `DocBlockResolverAwareTrait` — use `ServiceResolverAwareTrait`.
- Internal `DependencyProviderResolver` and the `AbstractFactory` dual-resolver
  path. `*DependencyProvider` classes are no longer auto-resolved; rename them.

### Documentation

- New `UPGRADE.md`: the 1.21 → 2.0 migration, ordered by likelihood of hitting
  you — the PHP floor, the three renames, then static analysis.
- New `docs/getting-a-dependency.md`: one primary path per intent, with the rest
  listed alongside the situation where each is right.

### Internal

- `StaticStateCoverageTest` enumerates every `static` property under `src/`,
  populates them by resolving a real module, and requires each to be back at its
  declared default after a reset — guarding from the opposite end to
  `ResetCacheCoverageTest`.
- `ContainerForwardingCoverageTest` requires every public method of
  `Gacela\Container\Container` to be forwarded or listed with its reason. The
  decorator's docblock claimed implementing `ContainerInterface` made this
  unnecessary; that was never true, since 1.x promises never to extend the
  interface, so every capability since 1.0 landed on the concrete class where an
  unforwarded method compiles fine and is simply unreachable. `createScope()` is
  the one recorded non-forward.
- `Gacela::resetCache()` clears the shared plan cache, so plans are shared
  *within* a bootstrap rather than across one. It deliberately does **not** call
  `Container::resetStaticCaches()`: that was tried and cost `FileCacheBench`
  11-17%, for memory rather than correctness.
- `debug:container` reads `stats(): ContainerStats` instead of the untyped
  `getStats()` array, whose shape upstream excludes from BC.
- Infection 0.34's `ReturnRemoval` mutator reads every early `return` as
  behaviour; both jobs run `--show-mutations=max`, since the default caps the
  list at 20 and hid 13 of 33 escapes.
- Raised the `nikic/php-parser` floor to `^5.4`: Psalm 6.16 reads
  `Property::$hooks`, which only exists from 5.4.
- Refreshed the toolchain: `infection` `^0.29` → `^0.34`, plus phpstan and
  rector. `phpunit` stays on `^10.5`.
- `symfony-bridge/composer.json` matches the root package it ships from.
- A root `gacela.php` scoping the console to `src`. Without it `doctor` walked
  the whole repository and reported two errors on a clean checkout: `tests/`
  holds fixtures that are deliberately separate applications, several declaring
  their own pillar suffixes, which look misnamed under the root config.
- Removed Scrutinizer; it duplicated PHPStan, Psalm and php-cs-fixer, which
  already gate every pull request.

## [1.21.0](https://github.com/gacela-project/gacela/compare/1.20.0...1.21.0) - 2026-07-26

Static analysis now **types** the pillar accessors instead of suppressing them, and the
console gained checks for the mistakes that were previously silent. Both are worth having
before you migrate to 2.0.

### Added

- `gacela init` scaffolds a project's `gacela.php` — the one file you had to copy from the docs before anything ran. Refuses to overwrite unless `--force`
- PHPStan **types** `getFacade()`/`getFactory()`/`getConfig()` from `#[ServiceMap]` instead of suppressing them. A suppressed call is not a typed one: it evaluated to `mixed`, which switched off checking of everything reached *through* the accessor. Nothing to configure beyond the existing `phpstan-gacela.neon` include; the suppression stays as a fallback for classes declaring neither `#[ServiceMap]` nor a `@method` docblock, and goes away in 2.0
- PHPStan types `getProvidedDependency(Foo::class)` as `Foo`, so the class-string form needs no hand-written `@var`. String keys stay `mixed`
- `FacadeInterfaceInSyncRule` reports public Facade methods missing from the Facade's own `*FacadeInterface` — drift that is invisible until someone reads both files, by which point the fix is breaking. Only fires for a facade that implements the interface named after it
- `doctor` reports pillar classes whose filename does not match the class. Pillars resolve by *filename* suffix, so migrating `AbstractDependencyProvider` means renaming `DependencyProvider.php` to `Provider.php` too — miss it and the module silently stops resolving
- `doctor --strict` exits non-zero on warnings as well as errors, so it can gate CI
- `debug:graph --check` exits non-zero on a module dependency cycle. `--allowed-cycles=file.json` records the ones a reviewer accepted, each with a mandatory `reason`, and an entry that no longer matches a real cycle fails just as loudly — an allow-list that outlives what it allows is a mute button. Without `--check` the command stays exit-code-neutral
- `debug:graph --compare-to=base-graph.json` diffs against a previously captured graph and reports the change as markdown with a mermaid diagram. Writes nothing when the graph is unchanged, so CI can comment only when a pull request actually moves a boundary

### Changed

- `profile:report --format=json` and `debug:config` now raise a `JsonException` instead of printing an empty value when their payload cannot be encoded
- `validate:config` attributes an unloadable binding class to that binding (`Could not resolve binding: <key> (<error>)`) instead of reporting a separate "Could not check circular dependencies" line
- `debug:container <class>` no longer prints "Indentation shows dependency depth" — the container returns a flat list, so nothing was ever indented

### Documentation

- A Factory can declare its dependencies in its **constructor**: pillars resolve through the container, so autowiring applies to the Factory itself. This already worked and is now documented and tested
- `docs/rfc/0002` inventories every way to obtain a dependency (25 paths across 4 intents), as the basis for naming one primary path per intent in 2.0

## [1.20.0](https://github.com/gacela-project/gacela/compare/1.19.0...1.20.0) - 2026-07-25

### Added

- `AbstractFactory::make(class-string, params)`: build a domain object through the module container with autowiring, honouring `#[Inject]`/`#[Singleton]`/`#[Factory]`, with `params` overriding constructor arguments by name. Needs no bindings, so it works in a module with no Provider. Additive — `getProvidedDependency()` and hand-wired `create*()` are unchanged
- `make:module --minimal` (alias `--template=minimal`): scaffolds Facade and Factory only. Config and Provider are optional at runtime

### Changed

- `make:module`'s provider template is now attribute-first, leading with a typed `#[Provides]` method. `provideModuleDependencies()` still works
- The `AbstractDependencyProvider` deprecation notice now always fires. It went through `trigger_deprecation()` and was skipped when `symfony/deprecation-contracts` was absent — which is most apps, since it is not a runtime dependency. **Expect to start seeing this notice**; migrate to `AbstractProvider`
- `GacelaConfig::addMappingInterface()` now emits `E_USER_DEPRECATED`. Deprecated since 1.2.0 with no runtime signal until now. Use `addBinding()` — same arguments
- `make:module` and `make:file` now throw when a generated file cannot be written. A read-only target or full disk previously printed success, exited `0`, and wrote nothing
- An unresolvable health check now throws `HealthCheckNotResolvableException` instead of being skipped. A typo in `addHealthCheck(SomeCheck::class)` previously produced a report that looked healthy while the check never ran

### Removed

- `Gacela\Framework\Container\Locator::addSingleton()` — no production caller, and `Locator` is `@internal`. Seed through a `Container` plus `Locator::getInstance($container)`

### Fixed

- A module's `provideModuleDependencies()` no longer runs twice. Both provider resolvers share a normalized cache slot, so a modern Provider was registered once via `register()` and again directly — running non-idempotent provider bodies (counters, external registrations, logging) twice
- `validate:config` now detects circular dependencies. It matched the exception message case-sensitively against the wrong casing, so the check never fired once and a cyclic configuration exited `0`. It now catches `CircularDependencyException` by type and prints the full `A -> B -> A` chain. **Pre-existing cycles will now fail validation (exit `1`)** — if this runs in CI, expect it to surface them on the first run. Other resolution failures, previously discarded silently, are reported as warnings

### Documentation

- New `docs/upgrading.md`: every 1.x deprecation with its replacement. Note that migrating off `AbstractDependencyProvider` also requires renaming `DependencyProvider.php` to `Provider.php` — pillars resolve by filename suffix, so renaming only the class leaves the module unresolvable
- Documented `make()` and attribute-first module DI in `docs/container-configuration.md`, and the optional Config/Provider pillars in `docs/getting-started.md`

### Internal

- Mutation testing now runs in CI. `infection.json5` has required an MSI of 100 for a while but no workflow ran it, so the score had drifted below the bar

## [1.19.0](https://github.com/gacela-project/gacela/compare/1.18.0...1.19.0) - 2026-07-23

### Added

- `GacelaConfig::addBindingIf(key, value)`: register a binding only when the key is not already bound, so plugins can ship an overridable default (register-unless-overridden)

### Changed

- Confirmed PHP 8.5 support by adding it to the CI test matrix (the `>=8.1` requirement already permitted it)

### Fixed

- The opcache preload script (`resources/gacela-preload.php`) now requires PHP 8.1, matching the framework's `>=8.1`, instead of advertising and guarding an obsolete PHP 7.4 floor
- Health-check resolution no longer swallows container errors: a registered check whose container resolution throws now surfaces the real exception instead of silently falling back to a default instance and hiding the misconfiguration

### Documentation

- New `docs/production-performance.md`: a single checklist for running Gacela fast in production (file cache, `cache:warm`, opcache preload, autoloader optimisation, disabling unused event listeners, cross-request `#[Cacheable]` storage, `GACELA_CACHE_DIR`)
- Corrected the `#[Cacheable]` backtrace-cost note (measured ~0.08 µs/call on a warm hit, not 1–5 µs) and now recommend explicit `$method`/`$args` as the pattern for hot, cheap methods; added `CacheableBench` to guard the delta

## [1.18.0](https://github.com/gacela-project/gacela/compare/1.17.0...1.18.0) - 2026-07-20

### Added

- Framework lifecycle events, zero-cost when nothing listens: `GacelaBootstrapStarted`/`GacelaBootstrapFinished`, `ConfigInitialized`/`ConfigKeyRead`/`ConfigKeyNotFound`, `ServiceResolved` (once per id), `BindingRegistered`, `ProviderRegistered`, `CacheCleared`, `CacheWarmed`
- `Gacela\Framework\Testing\GacelaTestCase`: bootstrap isolation per test, config overrides, and event-backed assertions — `assertServiceResolved()`, `assertBindingRegistered()`, `recordedGacelaEventsOf()`
- Typed config accessors on `Config`/`AbstractConfig`: `getString()`, `getInt()`, `getFloat()`, `getBool()`, `getArray()` — concrete return types, `null` default means required, wrong type throws instead of coercing (int→float widening allowed); faster than `get()` + a manual cast
- Scalar contextual bindings: `$config->when(X)->needs('$paramName')->give(30)`
- `ArrayAccess` on the main container: `$container[Id::class]`, `isset`, assignment, `unset`
- `debug:module <Name>`: resolved Facade/Factory/Config/Provider, bindings, and dependency tree (`--json`, `--tree`)
- `debug:graph`: whole-app module dependency graph (`--format=text|mermaid|graphviz|json`)
- `make:module --template=service [--with-tests]`: scaffolds a module that runs out of the box — four pillars plus a `Domain` service, optionally with a `GacelaTestCase`-based facade test
- `CrossModuleViaFacadeRule`: optional `sharedNamespaces` allowlist for shared kernels

### Changed

- Bumped `gacela-project/container` to `^0.10.0`. It fixes transient resolutions sharing child instances; uncached construction gets slower (sub-microsecond per resolve), while Gacela's steady-state paths are unaffected because resolved classes are instance-cached
- Event dispatch is zero-cost when nothing listens (~20% faster warm resolves). BC note: custom `EventDispatcherInterface` implementations must add `hasListeners()`

### Fixed

- `APP_ENV` is read from a single source for both the env-suffixed config files and the merged-config cache key, so they can no longer disagree mid-process
- `ConfigLoader`'s read cache is keyed by reader and path: the same file under two readers no longer shares a cache entry
- `Gacela::resetCache()` also clears the glob cache, so config files added or removed on disk are seen by the next bootstrap in the same process
- `ProviderRegisteredEvent` no longer fires twice for a modern `AbstractProvider`
- `Config::getEventDispatcher()` returns a no-op dispatcher before bootstrap instead of throwing
- Re-bootstrapping rebuilds the event dispatcher; listeners from a previous bootstrap no longer leak
- The merged-config cache filename embeds an app-root hash: apps sharing a cache directory no longer read each other's config (old files are ignored and removed by `cache:clear`)

### Documentation

- New `docs/events.md` (dispatch model, event catalog, listener cookbook) and `docs/testing.md` (`GacelaTestCase`, `ContainerFixture`)
- Module boundaries in `docs/static-analysis.md`, config precedence in `docs/getting-started.md`, scalar bindings and the `#[Singleton]`/`#[Factory]` attributes in `docs/container-configuration.md`

## [1.17.0](https://github.com/gacela-project/gacela/compare/1.16.0...1.17.0) - 2026-07-18

### Fixed

- Contextual bindings (`$config->when(X)->needs(Y)->give(Z)`) now apply when resolving Gacela classes (factories, configs, providers); previously the global binding always won
- Health checks registered in `gacela.php` are no longer wiped when a project also ships `gacela-{APP_ENV}.php`; checks from the default and env config files now accumulate
- `bin/gacela` now exits 1 and writes to STDERR on every failure path (missing autoload, missing `symfony/console`, bootstrap failure, or any `Throwable`), instead of exiting 0/255 with a raw stack trace
- `cache:clear` is now registered in the console; it shipped in 1.13.0 but was never wired in
- `cache:clear` now also removes the custom-services cache file (`gacela-custom-services.php`)
- `cache:warm` attribute pre-warming now works; it called a non-existent method and silently swallowed the `Error`
- `cache:warm` no longer hides errors: module-discovery failures and per-class attribute-warm `Error`s are now reported instead of swallowed
- `cache:warm` no longer aborts the whole run when one module's facade resolution throws; the failing module is reported and skipped
- `cache:warm` no longer skips modules whose name merely contains `Test` (e.g. `TestimonialFacade`); the filter now anchors on `\Test\`/`\Tests\` segments
- All cache-clear paths now delete through a single guarded helper that tolerates a missing file and invalidates opcache, so stale cache files no longer survive a clear
- `Config` no longer re-runs the full `init()` on every access when the merged config is empty; initialization is tracked with a flag
- Resolving a deprecated `AbstractDependencyProvider` no longer fatals without `symfony/deprecation-contracts`; the `trigger_deprecation()` call is now guarded
- `bin/gacela --version` no longer drifts after releases; the version is derived at runtime from Composer metadata via `Gacela::version()`
- Calling an undocumented method through the resolver traits now throws `MissingClassDefinitionException`, instead of silently resolving to the caller's first `use` import
- `debug:container --stats` now shows statistics when combined with a class argument; the flag was registered but never read
- `list:modules` now prints `No modules match filter "..."` when nothing matches, instead of an empty table or no output
- `getExternalService()` no longer misreports a service registered under the key `'0'` as `Available keys: none`

### Added

- `FileCache::writeContentsAtomically(string $file, string $content): bool` atomically writes pre-rendered content with the same guarantees as `writeAtomically()`, which now wraps it

### Changed

- `AbstractFactory::singleton()` and `CacheableTrait::cached()` are now generic (`@template T`); static analysis infers the return type instead of `mixed`
- `validate:config` no longer prints the no-op "Checking configuration paths..." placeholder section
- `AbstractProvider::register()` is now `final`; overriding it silently disabled `#[Provides]` scanning — use `provideModuleDependencies()` instead
- Class resolvers now share one `Container` built once from the global bindings, instead of rebuilding one per resolver type; reset with `Gacela::resetCache()`
- `Gacela::bootstrap()` now batches file-cache writes into a single atomic write per cache file, instead of one full-file rewrite per newly discovered key

### Removed

- `cache:warm --parallel` option and `ParallelModuleWarmer` — provided no real concurrency (warming is CPU-bound), so it matched sequential warming; use `cache:warm`
- `Gacela\Framework\Event\ClassResolver\GenericEvent` — dead code; never dispatched
- `GacelaFileCache::isEnabledFromCacheConfig()` — dead code; use `GacelaFileCache::isEnabled()`

### Documentation

- `docs/module-health-checks.md` now documents `GacelaConfig::addHealthCheck()` and how checks surface in `bin/gacela doctor`
- `profile:report` help no longer claims operations are "automatically tracked"; it now shows a manual `Profiler::start()`/`stop()` example
- `validate:config` help no longer implies a missing `gacela.php` is flagged; the file is optional

## [1.16.0](https://github.com/gacela-project/gacela/compare/1.15.0...1.16.0) - 2026-07-15

### Fixed

- File caches degrade gracefully in read-only environments (e.g. a read-only project root inside a build sandbox) instead of fataling the bootstrap with `Directory "..." was not created`: the class-resolver caches fall back to in-memory resolution, the merged-config auto-warm becomes a no-op, and cache writes never throw or emit raw PHP warnings. Pre-warmed cache files inside a read-only directory remain readable, so warm-at-build/run-read-only deployments keep their cache hits.

### Added

- `WritableDirectory::isUsable()` answers whether a directory can hold cache files (creating it when missing), memoized per directory
- `FileCache::isPersistent()` reports whether entries reach disk or only live in memory; `FileCache::writeAtomically()` returns whether the file was written

## [1.15.0](https://github.com/gacela-project/gacela/compare/1.14.4...1.15.0) - 2026-06-05

### Added

- `GacelaConfig::addLazy()` registers lazy-loaded services that are only instantiated on first access, deferring expensive service creation to improve startup performance
- `debug:config` console command prints the effective merged configuration (optionally filtered by a key substring), plus a `Config::getAllValues()` accessor
- Class-resolution failures now list the exact class-name candidates that were tried, making naming-convention and namespace mismatches easier to diagnose

### Changed

- Merged config cache now auto-warms on miss: when file cache is enabled (`GacelaConfig::enableFileCache()`), the first bootstrap persists the merged config so subsequent bootstraps skip globbing and parsing configuration files — no manual `cache:warm` required
- Bump `gacela-project/container` to `^0.8.1` for PHP 8.5 compatibility (removes deprecated `SplObjectStorage::attach()`/`detach()` usage)

## [1.14.4](https://github.com/gacela-project/gacela/compare/1.14.3...1.14.4) - 2026-04-20

### Added

- Windows support: `windows-latest` now part of the CI matrix

### Fixed

- Cache-dir resolution on Windows (drive-letter regex, separator handling)
- Platform-independent exception messages in `ClassResolverExceptionTrait`
- `FileCache` normalizes its directory input (trim, fold separators, preserve UNC, strip embedded Windows absolute path)

## [1.14.3](https://github.com/gacela-project/gacela/compare/1.14.2...1.14.3) - 2026-04-17

### Changed

- Upgrade to PHPStan 2.x (`phpstan/phpstan ^2.0`, `phpstan/phpstan-strict-rules ^2.0`) and Rector 2.x. Built-in Gacela PHPStan rules migrated to the 2.x rule API (`RuleErrorBuilder`, `getParents()`).

## [1.14.2](https://github.com/gacela-project/gacela/compare/1.14.1...1.14.2) - 2026-04-17

### Added

- `GacelaConfig::setAppModulePaths()` to scope module discovery to specific directories

### Fixed

- `list:modules` / `debug:modules` no longer warn on top-level dotfile PHP configs
- `validate:config` stays silent when `gacela.php` is missing (file is optional)

## [1.14.1](https://github.com/gacela-project/gacela/compare/1.14.0...1.14.1) - 2026-04-16

### Fixed

- Correct CLI version in `bin/gacela` (was still showing 1.13.0 in 1.14.0 tag)

## [1.14.0](https://github.com/gacela-project/gacela/compare/1.13.0...1.14.0) - 2026-04-16

### Added

- `CacheableTrait` in `AbstractFacade` — facades can now use `#[Cacheable]` out of the box
- `#[Inject]` constructor-parameter attribute with optional `implementation` override; `debug:dependencies` surfaces it
- `gacela/symfony-bridge`: `GacelaInjectCompilerPass` routes `#[Inject]` parameters through Gacela's container in Symfony apps
- `#[Provides('ID')]` attribute for declarative provider registration
- `FileCache<T>` typed file-backed cache with TTL, batching, and atomic writes
- `ScopedCache` decorator with dependency graph, cascading invalidation, and cycle detection
- `GacelaConfig::addHandlerRegistry()` for provider-registered dispatch tables
- `GacelaConfig::addHealthCheck()` for provider-based health checks
- `ContainerFixture` testing trait for PHPUnit container isolation

### Changed

- `CacheableTrait::cached()` infers method and args from the caller automatically
- `#[Cacheable]` storage is pluggable via `CacheStorageInterface`; supports per-method TTL overrides and `{N}` key placeholders
- `MergedConfigCache` uses `FileCache::writeAtomically()` for atomic writes

### Fixed

- `ResolvableType::fromClassName()` now uses `str_ends_with` to correctly match suffix types (e.g. `FacadeFactory` no longer misresolves as `Facade`)
- `AllAppModulesFinder::buildClassName()` handles filenames with a leading dot correctly
- `GacelaConfig::getExternalService()` throws `InvalidArgumentException` on missing key instead of silently returning null

### Performance

- `#[Cacheable]` hot path: memoized reflection, miss sentinel, scalar-key fast-path

## [1.13.0](https://github.com/gacela-project/gacela/compare/1.12.0...1.13.0) - 2026-04-15

### Added

#### Commands

- `cache:clear` command to remove all Gacela cache files
- `cache:warm --parallel` flag for parallel cache warming via PHP 8.1 Fibers, up to 5× faster
- `cache:warm --attributes` flag to pre-scan and cache `#[ServiceMap]` attributes
- `debug:dependencies <class|file>` command that inspects a class's constructor and reports each parameter's resolvability through the container (bound → target, autowirable, has default, or unresolvable with a reason). Accepts either a fully qualified class name or a path to a PHP file that declares the class.
- `debug:modules [filter]` command that walks every discovered Gacela module and inspects the constructor of each pillar (Facade, Factory, Config, Provider). Default output groups by module with per-pillar resolvable/unresolvable counts; `--detail` shows every parameter. Complements `list:modules` (structural view) and `debug:dependencies` (single-class deep dive).
- `doctor` command aggregating environmental and wiring health checks (cache staleness, suffix mismatches) with per-check remediation hints
- `profile:report` command to generate and analyze performance reports

#### Dependency injection

- Contextual bindings via `GacelaConfig::when()`
- Service aliases via `GacelaConfig::addAlias()`
- Protected services via `GacelaConfig::addProtected()`
- `Gacela::getRequired()` and `Locator::getRequired()` for type-safe service resolution that throws `ServiceNotFoundException` instead of returning null

#### Facades

- `#[Cacheable]` attribute and `CacheableTrait` for automatic facade-method result caching with TTL

#### Observability

- `Profiler` for performance profiling and bottleneck detection
- Custom per-module health checks via `ModuleHealthCheckInterface`, `HealthChecker`, `HealthStatus` (OK / WARNING / ERROR / CRITICAL), and `HealthCheckReport`

### Changed

- Exception messages now include did-you-mean suggestions and actionable examples via `ErrorSuggestionHelper`
- `Locator::getRequired()` now passes the container's registered services and bindings to `ServiceNotFoundException`, so the existing did-you-mean suggestions are produced when a typo'd service is requested
- `validate:config` reports binding-mismatch warnings with the expected interface/class, the actual type chain of the bound value, and a fix hint; interface-keyed bindings are now also checked (previously skipped)

### Performance

- Persist the merged file-based config to disk via `MergedConfigCache` so bootstraps skip globbing and parsing configuration files; produced by `cache:warm`, removed by `cache:clear`, keyed per `APP_ENV`
- `cache:warm` now pre-populates the `ClassNamePhpCache` by running Gacela's resolvers against each module's Facade, so first requests skip the cold `namespaces × rules × types × class_exists` lookup in `ClassNameFinder`
- `ClassValidator` memoizes `class_exists()` results so repeated candidate lookups across `namespaces × rules × types` reuse the autoloader probe within a request
- Share `ReflectionClass` instances between `DocBlockResolver` and `CacheWarmService` via a `ReflectionClassPool` to avoid re-reflecting the same class
- `cache:warm` batches `AbstractPhpFileCache` writes via new `beginBatch()`/`commitBatch()` and flushes with an atomic `rename()` so a single file write replaces the previous _N modules × 4 resolvers_ full-file rewrites. Also removes the risk of a half-written cache file if the warm process is interrupted mid-write

### Documentation

- Trimmed README and docs for clarity; added `docs/README.md` as a navigable index

## [1.12.0](https://github.com/gacela-project/gacela/compare/1.11.0...1.12.0) - 2025-11-09

- Renamed `DocBlockResolver` to `ServiceResolver` to better reflect its purpose
- Added `ServiceResolverAwareTrait` with caching improvements; will replace `DocBlockResolverAwareTrait`
- Introduced the `#[ServiceMap]` attribute as the preferred service binding instead of `DocBlock`
- Added `cache:warm` command to pre-resolve module classes for optimal production performance
- Added `validate:config` command to validate Gacela configuration for errors and best practices
- Added opcache preload script for 20-30% performance boost in production
- Added suppressions to `phpstan-gacela.neon` and `psalm-gacela.xml` for dynamic resolution
- Improved error messages with actionable suggestions and examples
- Added `GacelaConfig::addFactory()` to register factory services that create new instances on each resolution
- Added module-boundary PHPStan rules to `phpstan-gacela.neon`:
  - `FacadeOnlyDelegatesRule`: Facade methods must only delegate to `$this->getFactory()`, `getConfig()`, or `getProvider()`
  - `FactoryDoesNotCallFacadeRule`: Factories must not instantiate Facades or call `$this->getFacade()`
  - `CrossModuleViaFacadeRule` (opt-in): cross-module references (new/static call/const fetch) must go through a `*Facade`

## [1.11.0](https://github.com/gacela-project/gacela/compare/1.10.0...1.11.0) - 2025-10-12

- Add `phpstan-gacela.neon` for reusable PHPStan rules enforcing Gacela naming conventions (Facade, Factory, Provider, Config)
- Drop static facade magic methods; call `$facade->getFactory()` directly
- Improve PHPStan generic type support
  - Replace `@method` annotations with `@extends` for better type inference
- Improve `SetupGacela`; extract `PropertyChangeTracker` and `SetupGacelaProperties`
- Run CI tests with PHP 8.4

## [1.10.0](https://github.com/gacela-project/gacela/compare/1.9.1...1.10.0) - 2025-08-02

- Fix default cache dir
- Improve internal `AnonymousGlobal::getByKey()`
- Add internal cache on `PathFinder` and `GlobalKey`
- Added factory instance caching via new `singleton()` helper

## [1.9.1](https://github.com/gacela-project/gacela/compare/1.9.0...1.9.1) - 2024-12-12

- Better compatibility with PHP 8.4

## [1.9.0](https://github.com/gacela-project/gacela/compare/1.8.1...1.9.0) - 2024-12-01

- Compatibility with PHP 8.4
- Added `GACELA_CACHE_DIR` env variable to override where to place the cache files
- Added `RELEASE.md` docs

## [1.8.1](https://github.com/gacela-project/gacela/compare/1.8.0...1.8.1) - 2024-11-09

- Internal optimizations

## [1.8.0](https://github.com/gacela-project/gacela/compare/1.7.1...1.8.0) - 2024-08-17

- Moved `./gacela` script to `bin/` directory
- Fixed disable event listeners
- Added `Gacela::addGlobal()`
- Added `Gacela::overrideExistingResolvedClass()`
- Deprecated `AbstractDependencyProvider` in favor of `AbstractProvider`

## [1.7.1](https://github.com/gacela-project/gacela/compare/1.7.0...1.7.1) - 2024-04-16

- Keep packages sorted in composer.json
- Added `ergebnis/composer-normalize`
- Added `rector`

## [1.7.0](https://github.com/gacela-project/gacela/compare/1.6.0...1.7.0) - 2023-12-21

- Change min PHP support for `PHP>=8.1`

## [1.6.0](https://github.com/gacela-project/gacela/compare/1.5.0...1.6.0) - 2023-10-15

- Fixed combining event listeners from different `SetupGacela` objects
- Removed `ConfigNotFoundException`
- Simplify `FactoryResolverAwareTrait`
- Refactor `SetupGacela` and `FactoryResolver`

## [1.5.0](https://github.com/gacela-project/gacela/compare/1.4.0...1.5.0) - 2023-07-01

- Added command `gacela list:modules [--detailed|-d]`
- Fixed Windows support

## [1.4.0](https://github.com/gacela-project/gacela/compare/1.3.0...1.4.0) - 2023-05-20

- Added `Gacela::rootDir()`
- Added `GacelaConfig::enableFileCache()`
- Added plugins as callable
    - `GacelaConfig::addPlugin(string|callable)`
- Rename `addExtendConfig()` to `extendGacelaConfig()` in `GacelaConfig`
- Removed deprecated `withPhpConfigDefault()`

## [1.3.0](https://github.com/gacela-project/gacela/compare/1.2.0...1.3.0) - 2023-05-08

- Deleted `PluginInterface`
  - A plugin can be any class that implements `__invoke()`
- Added `GacelaConfig::addExtendConfig()`
- Remove the deprecated methods `setFileCacheEnabled()` & `setFileCacheDirectory()`

## [1.2.0](https://github.com/gacela-project/gacela/compare/1.1.1...1.2.0) - 2023-04-29

- Unify `setFileCacheEnabled` and `setFileCacheDirectory` into one single method: `setFileCache(bool $enabled, string $dir)`. Deprecated the former methods
- Rename dependency; from `resolver` to `container`.
- Moved the current `Container` logic to the decoupled `container` dependency
- Add "plugins" to run right after the `Gacela::bootstrap()`
- Deprecated `addMappingInterface()` in favor of `addBinding()`

## [1.1.1](https://github.com/gacela-project/gacela/compare/1.1.0...1.1.1) - 2023-04-19

- Deprecate `withPhpConfigDefault()` in favor of `defaultPhpConfig()`
- Extract the dependency resolver logic into a different repo `gacela-project/resolver`

## [1.1.0](https://github.com/gacela-project/gacela/compare/1.0.1...1.1.0) - 2023-03-21

- Allow using static facade methods
  - Enabled calling `::getFactory()` from a static context 
- ResetInMemoryCache also from anonymous globals and factory containers

## [1.0.1](https://github.com/gacela-project/gacela/compare/1.0.0...1.0.1) - 2023-03-12

- Normalise internal events' `toString()`
- Bugfix Register only once specific events on bootstrap

## [1.0.0](https://github.com/gacela-project/gacela/compare/0.32.0...1.0.0) - 2023-01-01

- Allow extending raw arrays as services
- The Locator cannot resolve any more interface classes only because of the `Interface` suffix in their name
- Drop support for PHP 7.4

## [0.32.0](https://github.com/gacela-project/gacela/compare/0.31.0...0.32.0) - 2022-11-24

- Froze a "Container service" after its first usage with `get()`
- Added `Container::protect(service)`

## [0.31.0](https://github.com/gacela-project/gacela/compare/0.30.1...0.31.0) - 2022-11-15

- Added `Container::factory(service)`
- Added `Container::extend(id, service)`
- Added `GacelaConfig::extendService(id, service)`

## [0.30.1](https://github.com/gacela-project/gacela/compare/0.30.0...0.30.1) - 2022-11-09

- Fixed `DocBlockResolver` resolvableType
- Fixed `DocBlockResolverAwareTrait` cache

## [0.30.0](https://github.com/gacela-project/gacela/compare/0.29.0...0.30.0) - 2022-11-07

- Allow combine and override different `GacelaConfig` from project level
- Added internal events for the `ClassResolver\Cache` scope
- Fixed `PhpFileCache` bug

## [0.29.0](https://github.com/gacela-project/gacela/compare/0.28.0...0.29.0) - 2022-11-02

- Added `GacelaConfig::registerSpecificListener(event, listener)`
- Added `GacelaConfig::registerGenericListener(listener)`

## [0.28.0](https://github.com/gacela-project/gacela/compare/0.27.0...0.28.0) - 2022-10-27

- Add file cache for resolved classes
- Remove profiler, because it does the same as the file cache system under the hood

## [0.27.0](https://github.com/gacela-project/gacela/compare/0.26.0...0.27.0) - 2022-10-12

- Read autoload-dev psr-4 namespaces for gacela make commands
- Cache default resolved gacela class
- Allow optional project namespace on class name finder rules

## [0.26.0](https://github.com/gacela-project/gacela/compare/0.25.0...0.26.0) - 2022-10-01

- Added new feature: gacela file profiler (disabled by default)
- Removed gacela file cache. Instead, use InMemoryCache always
- Removed `gacela cache:clear` command

## [0.25.0](https://github.com/gacela-project/gacela/compare/0.24.0...0.25.0) - 2022-09-18

- Removed deprecated `SetupGacelaInterface` from `gacela.php`
- Allow using abstracts Factory and Config by default
- Create `gacela cache:clear` command
- Process configFn from appRootDir if exists, and it wasn't defined on bootstrap

## [0.24.0](https://github.com/gacela-project/gacela/compare/0.23.1...0.24.0) - 2022-07-23

- Change cache default directory to `.gacela/cache`
- Added project namespaces
  - `GacelaConfig::setProjectNamespaces(array)` to be able to resolve gacela classes with priorities
- Added gacela configuration for different environments
- Allow adding config key-values from GacelaConfig
  - `GacelaConfig::addAppConfigKeyValue(string, mixed)`
  - `GacelaConfig::addAppConfigKeyValues( array<string, mixed> )`
- When cache is disabled on bootstrap, Gacela won't generate `*.cache` files

## [0.23.1](https://github.com/gacela-project/gacela/compare/0.23.0...0.23.1) - 2022-06-25

- Fix `setCacheDirectory()` with nested dir levels

## [0.23.0](https://github.com/gacela-project/gacela/compare/0.22.0...0.23.0) - 2022-06-24

- Group gacela cache files inside a `cache/` directory
- Allow enabling/disabling cache files from the project config files
- Added `setCacheDirectory()` to `GacelaConfig`
- Added `vendor/bin/gacela` script
- Add `.editorconfig` & `.gitattributes` files
- Ignore `composer.lock`

## [0.22.0](https://github.com/gacela-project/gacela/compare/0.21.0...0.22.0) - 2022-06-10

- Added a (file) cache layer 
  - for class-names to their resolvable-type (in a file: `.gacela-class-names.cache`)
  - for custom-services to their resolvable-class (in a file: `.custom-services.cache`)
- Delete unnecessary Backtrace for exceptions
- Rename resetCache() to setCacheEnabled() from `GacelaConfig`

## [0.21.0](https://github.com/gacela-project/gacela/compare/0.20.0...0.21.0) - 2022-05-29

- Allow only a `Closure(GacelaConfig):void` object to 2nd parameter type of `Gacela::bootstrap()`
- Add new key Gacela configuration key: `GacelaConfig::setResetCache(bool)`

## [0.20.0](https://github.com/gacela-project/gacela/compare/0.19.0...0.20.0) - 2022-05-27

- Add `GacelaConfig::withPhpConfigDefault()`
- Allow gacela anon-classes using parent methods
- Define local pattern php config default
- Add `AbstractClassResolver::resetCache()`

## [0.19.0](https://github.com/gacela-project/gacela/compare/0.18.1...0.19.0) - 2022-05-19

- Removed bin/gacela util from this repo
  - CodeGenerator moved to its own repo: `gacela-project/gacela-cli`

## [0.18.1](https://github.com/gacela-project/gacela/compare/0.18.0...0.18.1) - 2022-05-15

- Bugfix SetupGacela using proper method from parent class

## [0.18.0](https://github.com/gacela-project/gacela/compare/0.17.2...0.18.0) - 2022-05-14

- Removed default config path from config/*.php to empty
- Added allow gacela.php using a callable with GacelaConfig arg
- Moved namespace from Setup to Bootstrap (affecting SetupGacela)
  - Deprecated Setup namespace in favor of Bootstrap
- Remove deprecated `globalServices()` method
- Deprecate SetupGacelaInterface from gacela.php and `Gacela::bootstrap()`. Use callable(GacelaConfig) instead

## [0.17.2](https://github.com/gacela-project/gacela/compare/0.17.1...0.17.2) - 2022-05-02

- Ensure GLOB_BRACE constant is defined for Alpine and Solaris OS

## [0.17.1](https://github.com/gacela-project/gacela/compare/0.17.0...0.17.1) - 2022-05-02

- Removing illegal c-char from filename

## [0.17.0](https://github.com/gacela-project/gacela/compare/0.16.0...0.17.0) - 2022-04-29

- Added DocBlockResolverAwareTrait
- Deprecated FacadeResolverAwareTrait in favor of DocBlockResolverAwareTrait
- Removed deprecated setup as array in `Gacela::bootstrap()`
- Allow overriding Gacela resolvable Facade type

## [0.16.0](https://github.com/gacela-project/gacela/compare/0.15.0...0.16.0) - 2022-04-14

- Combine gacela file and bootstrap setup
- Rename the concept of GlobalServices to ExternalServices
- Make the Facade accessible from module-internal sub-folders
- Allow to return an instance of SetupGacela on gacela.php

## [0.15.0](https://github.com/gacela-project/gacela/compare/0.14.0...0.15.0) - 2022-03-26

- Updated ClassInfo improve performance adding cache
- Renamed GlobalServices to Setup
- Added SetupGacela to replace AbstractConfigGacela
- Added support for dark mode logo

## [0.14.0](https://github.com/gacela-project/gacela/compare/0.13.0...0.14.0) - 2022-03-14

- Updated from protected to public the `getAppRootDir()` from `AbstractConfig`
- Updated `AbstractConfigGacela` to use builders instead of returning arrays

## [0.13.0](https://github.com/gacela-project/gacela/compare/0.12.0...0.13.0) - 2022-03-01

- Added allow defining a config reader as class-string too
- Moved the "config readers" next to their config item itself
  - Performance improvement specially when using different config readers in the same project
- Added OverrideResolvableTypes feature
  - Allow overriding Gacela resolvable types (Factory, Config, DependencyProvider)
- Removed deprecated methods `getApplicationRootDir()` & `setApplicationRootDir()` from Config
  - Use `getAppRootDir()` & `setAppRootDir()` instead
- Deprecated and removed `CustomService` feature. Use `MappingInterfaces` feature instead
  - Why? Too much magic

## [0.12.0](https://github.com/gacela-project/gacela/compare/0.11.0...0.12.0) - 2022-02-13

- Added `getAppRootDir()` to AbstractConfig
- Added `APP_ENV` environment key, to define different config files on different environments
- Added `'config-readers'` key in the globalServices and `gacela.php`
- Added `'custom-services-location'` key in the globalServices and `gacela.php`
  - Define namespaces (relative to a module) where Gacela should check for custom services that will be auto-resolved
- Deprecated `getApplicationRootDir()` from Config. Use `getAppRootDir()` instead
- Removed `EnvConfigReader` from `gacela-project/gacela`
  - If you want to read `.env` values, you should require `gacela-project/gacela-env-config-reader`

## [0.11.0](https://github.com/gacela-project/gacela/compare/0.10.0...0.11.0) - 2022-01-18

- Deleted deprecated array config in `gacela.php`
- Allow `null` as default config value
- The globalServices are passed into `mappingInterfaces()` and not as constructor argument

## [0.10.0](https://github.com/gacela-project/gacela/compare/0.9.0...0.10.0) - 2021-10-04

- Allow setup custom config from `Gacela::bootstrap()` directly

## [0.9.0](https://github.com/gacela-project/gacela/compare/0.8.0...0.9.0) - 2021-08-27

- Allow return JsonSerializable objects in PHP config files

## [0.8.0](https://github.com/gacela-project/gacela/compare/0.7.0...0.8.0) - 2021-08-16

- Updated `gacela.php` config file:
  - returning a simple array has been deprecated
  - an anonymous function that creates an anonymous class that extends from AbstractConfigGacela should be used
- Remove deprecated `gacela.json` config file

## [0.7.0](https://github.com/gacela-project/gacela/compare/0.6.0...0.7.0) - 2021-08-07

- Improve the flexibility from the ConfigReaders
- Deprecated `gacela.json` config file, in favor of `gacela.php`
- Added 'mapping-interfaces' key to `gacela.php`
- Added autowiring for Factory dependencies

## [0.6.0](https://github.com/gacela-project/gacela/compare/0.5.0...0.6.0) - 2021-07-27

- Added `AbstractClassResolver::overrideExistingResolvedClass()`
- Locator uses `AbstractClassResolver::getGlobalInstance()` before creating a new instance
- Unify the cacheKey using `GlobalKey`

## [0.5.0](https://github.com/gacela-project/gacela/compare/0.4.0...0.5.0) - 2021-07-19

- `Config::setConfigReaders()` create a new config instance singleton
- Added `AbstractClassResolver::addAnonymousGlobal()` you can now use anonymous classes
- Added matrix for the GitHub CI for diff PHP versions (7.4,8.0), and diff OS (mac,linux,windows)

## [0.4.0](https://github.com/gacela-project/gacela/compare/0.3.0...0.4.0) - 2021-07-10

- Allow multiple (and different) config files defined in `gacela.json`
- Make extensible the Config Readers

## [0.3.0](https://github.com/gacela-project/gacela/compare/0.2.0...0.3.0) - 2021-07-04

- Allow using config php and env files defined in `gacela.json`
- Use long name by default in the generator code commands. Optional short names

## [0.2.0](https://github.com/gacela-project/gacela/compare/0.1.0...0.2.0) - 2021-04-27

- Added CodeGenerator
- Refactoring Config reading all php files from config directory

## [0.1.0](https://github.com/gacela-project/gacela/compare/690484441389a2d3bd921ab7f278c6d945f50cac...0.1.0) - 2021-04-02

- Added Facade, Factory, Config and DependencyProvider basic functionality
- Provide documentation for each of these concepts with examples
