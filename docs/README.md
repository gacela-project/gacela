# Gacela Documentation

- [Getting started](getting-started.md) — install and build your first module
- [CLI commands](cli.md) — every `vendor/bin/gacela` command, what it is for
- [Getting a dependency](getting-a-dependency.md) — one primary path per intent, and when the other paths are right
- [Container configuration](container-configuration.md) — factories, aliases, contextual bindings
- [Config schema](config-schema.md) — declare what the configuration must contain, and fail before a request does
- [Static analysis](static-analysis.md) — the architecture rules, for PHPStan and Psalm alike
- [Module boundaries](module-boundaries.md) — the cross-module rules, dependency-cycle gate, declared rules file, and CI graph review
- [Module health checks](module-health-checks.md) — report module operational status
- [Profiling](profiling.md) — instrument code with the in-memory `Profiler` and read it back with `profile:report`
- [Events](events.md) — listen to Gacela internals: dispatch model, event catalog, cookbook
- [Testing](testing.md) — `GacelaTestCase`: bootstrap isolation, config overrides, container assertions
- [Caching](caching.md) — overview of Gacela's three caching layers and when to reach for each
- [Cacheable methods](cacheable-methods.md) — cache facade method results with `#[Cacheable]`
- [FileCache and ScopedCache](file-cache.md) — cache arbitrary application data with atomic writes and cascading invalidation
- [Opcache preload](opcache-preload.md) — production performance tuning
- [Production performance](production-performance.md) — full checklist to run Gacela fast in production
- [Upgrading](../UPGRADE.md) — why to move to 2.0, and every breaking change with its replacement

## Host frameworks

- [Symfony bundle](../symfony-bridge/README.md) — bootstrap Gacela from the kernel, reach Symfony services, `bin/console gacela:*`
- [Laravel provider](../laravel-bridge/README.md) — bootstrap Gacela when the app boots, reach Laravel services, `artisan gacela:*`

## RFCs

- [RFC-0001: `#[Inject]` + `#[ServiceMapTyped]` — Symfony DI interop](rfc/0001-inject-symfony-di-interop.md)
- [RFC-0002: How many ways are there to obtain a dependency?](rfc/0002-dependency-paths-inventory.md) — the 25-path inventory behind the 2.0 "one primary path" question
- [RFC-0003: The bootstrap configuration surface](rfc/0003-bootstrap-configuration-surface.md) — the competition test, the naming grammar, and the audited `GacelaConfig` table that gates new methods

Full reference: [gacela-project.com](https://gacela-project.com/)
