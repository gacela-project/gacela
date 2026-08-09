# Gacela Documentation

- [Getting started](getting-started.md) — install and build your first module
- [CLI commands](cli.md) — every `vendor/bin/gacela` command, what it is for
- [Getting a dependency](getting-a-dependency.md) — one primary path per intent, and when the other paths are right
- [Container configuration](container-configuration.md) — factories, aliases, contextual bindings
- [Static analysis](static-analysis.md) — the architecture rules, for PHPStan and Psalm alike
- [Module health checks](module-health-checks.md) — report module operational status
- [Events](events.md) — listen to Gacela internals: dispatch model, event catalog, cookbook
- [Testing](testing.md) — `GacelaTestCase`: bootstrap isolation, config overrides, container assertions
- [Caching](caching.md) — overview of Gacela's three caching layers and when to reach for each
- [Cacheable methods](cacheable-methods.md) — cache facade method results with `#[Cacheable]`
- [Opcache preload](opcache-preload.md) — production performance tuning
- [Production performance](production-performance.md) — full checklist to run Gacela fast in production
- [Upgrading](../UPGRADE.md) — why to move to 2.0, and every breaking change with its replacement

## RFCs

- [RFC-0001: `#[Inject]` + `#[ServiceMapTyped]` — Symfony DI interop](rfc/0001-inject-symfony-di-interop.md)
- [RFC-0002: How many ways are there to obtain a dependency?](rfc/0002-dependency-paths-inventory.md) — the 25-path inventory behind the 2.0 "one primary path" question

Full reference: [gacela-project.com](https://gacela-project.com/)
