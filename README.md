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

## Gacela helps you build modular applications

**VISION**: Simplify the communication of your different modules in your web application.

**MISSION**: Normalize the entry point of a module, without interfering with your domain-business logic.

Splitting your project into different modules help in terms of maintainability and scalability.
It encourages your modules to interact with each other in a unified way by following these rules:

- Modules interact with each other **only** via their **Facade**
- The [**Facade**](https://gacela-project.com/docs/facade/) is the *entry point* of a module
- The [**Factory**](https://gacela-project.com/docs/factory/) manage the *intra-dependencies* the module
- The [**Provider**](https://gacela-project.com/docs/provider/) resolves the *extra-dependencies* of the module
- The [**Config**](https://gacela-project.com/docs/config/) access the project's *config files*

### Installation

```bash
composer require gacela-project/gacela
```

### Module structure

You can prefix gacela classes with the module name to improve readability. See more [about gacela](https://gacela-project.com/about-gacela/).

An example of an application structure using gacela modules:

```bash
application-name
├── gacela.php
├── config
│   └── ...
│
├── src
│   ├── ModuleA
│   │   ├── Domain
│   │   │   └── ...
│   │   ├── Application
│   │   │   └── ...
│   │   ├── Infrastructure
│   │   │   └── ...
│   │   │ # These are the 4 "gacela classes":
│   │   ├── Facade.php
│   │   ├── Factory.php
│   │   ├── Provider.php
│   │   └── Config.php
│   │
│   └── ModuleB
│       └── ...
│
├── tests
│   └── ...
└── vendor
    └── ...
```

### Documentation

You can check the full documentation in the official [website](https://gacela-project.com/).

### Examples

You can see examples using gacela in [this repository](https://github.com/gacela-project/gacela-example).

### Contribute

You are more than welcome to contribute reporting 
[issues](https://github.com/gacela-project/gacela/issues), 
sharing [ideas](https://github.com/gacela-project/gacela/discussions),
or [contributing](.github/CONTRIBUTING.md) with your Pull Requests.

---

> Inspired by Spryker Framework: https://github.com/spryker
