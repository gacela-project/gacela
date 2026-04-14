<p align="center">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="gacela-logo-dark.svg">
      <img alt="Gacela logo" src="gacela-logo.svg" width="400">
    </picture>
</p>

<p align="center">
  <a href="https://github.com/gacela-project/gacela/actions/workflows/tests.yml">
    <img src="https://github.com/gacela-project/gacela/actions/workflows/tests.yml/badge.svg" alt="GitHub Build Status">
  </a>
  <a href="https://scrutinizer-ci.com/g/gacela-project/gacela/?branch=main">
    <img src="https://scrutinizer-ci.com/g/gacela-project/gacela/badges/quality-score.png?b=main" alt="Scrutinizer Code Quality">
  </a>
  <a href="https://scrutinizer-ci.com/g/gacela-project/gacela/?branch=main">
    <img src="https://scrutinizer-ci.com/g/gacela-project/gacela/badges/coverage.png?b=main" alt="Scrutinizer Code Coverage">
  </a>
  <a href="https://shepherd.dev/github/gacela-project/gacela">
    <img src="https://shepherd.dev/github/gacela-project/gacela/coverage.svg" alt="Psalm Type-coverage Status">
  </a>
  <a href="https://dashboard.stryker-mutator.io/reports/github.com/gacela-project/gacela/main">
    <img src="https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fgacela-project%2Fgacela%2Fmain" alt="Mutation testing badge">
  </a>
  <a href="https://github.com/gacela-project/gacela/blob/master/LICENSE">
    <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="MIT Software License">
  </a>
</p>

## Gacela — build modular PHP applications

Gacela normalizes module boundaries so parts of your application communicate through a single entry point, without leaking internals.

Each module exposes four classes:

- [**Facade**](https://gacela-project.com/docs/facade/) — public API, the only way in
- [**Factory**](https://gacela-project.com/docs/factory/) — creates internal services
- [**Provider**](https://gacela-project.com/docs/provider/) — wires external dependencies
- [**Config**](https://gacela-project.com/docs/config/) — reads project config

## Installation

```bash
composer require gacela-project/gacela
```

## Module structure

```
app/
├── gacela.php
├── config/
└── src/
    └── ModuleA/
        ├── Facade.php
        ├── Factory.php
        ├── Provider.php
        └── Config.php
```

## Documentation

- [Getting started](docs/getting-started.md)
- [Container configuration](docs/container-configuration.md)
- [Static analysis (PHPStan / Psalm)](docs/static-analysis.md)
- [Module health checks](docs/module-health-checks.md)
- [Opcache preload](docs/opcache-preload.md)
- Full reference: [gacela-project.com](https://gacela-project.com/)
- Examples: [gacela-example](https://github.com/gacela-project/gacela-example)

## Contributing

Report [issues](https://github.com/gacela-project/gacela/issues), share [ideas](https://github.com/gacela-project/gacela/discussions), or open a [pull request](.github/CONTRIBUTING.md).

---

> Inspired by [Spryker](https://github.com/spryker).
