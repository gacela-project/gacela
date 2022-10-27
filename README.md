<p align="center">
  <img src="gacela-logo.svg" width="350" alt="Gacela logo"/>
</p>

<p align="center">
  <a href="https://github.com/gacela-project/gacela/actions">
    <img src="https://github.com/gacela-project/gacela/workflows/CI/badge.svg" alt="GitHub Build Status">
  </a>
  <a href="https://scrutinizer-ci.com/g/gacela-project/gacela/?branch=master">
    <img src="https://scrutinizer-ci.com/g/gacela-project/gacela/badges/quality-score.png?b=master" alt="Scrutinizer Code Quality">
  </a>
  <a href="https://scrutinizer-ci.com/g/gacela-project/gacela/?branch=master">
    <img src="https://scrutinizer-ci.com/g/gacela-project/gacela/badges/coverage.png?b=master" alt="Scrutinizer Code Coverage">
  </a>
  <a href="https://shepherd.dev/github/gacela-project/gacela">
    <img src="https://shepherd.dev/github/gacela-project/gacela/coverage.svg" alt="Psalm Type-coverage Status">
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

- The [`Facade`](https://gacela-project.com/docs/facade/) is the entry point of your module, and has direct access to the Factory.
- The [`Factory`](https://gacela-project.com/docs/factory/) resolves the intra-dependencies of your module's classes, and has access to the Config.
- The [`Config`](https://gacela-project.com/docs/config/) has access to the key-values from your config files.
- The [`DependencyProvider`](https://gacela-project.com/docs/dependency-provider/) resolves the extra-dependencies of your module.

### Installation

```bash
composer require gacela-project/gacela
```

### Module structure

You can prefix gacela classes with the module name to improve readability. See more [about gacela](https://gacela-project.com/about-gacela/).

An example of an application structure using gacela modules:

```bash
application-name
├── gacela.php                     # You can customize some behaviours of gacela.
│
├── config
│   ├── default.php
│   └── local.php
│
├── public
│   └── index.php                  # An example of your application entry point.
│
├── src
│   ├── ExampleModuleWithoutPrefix
│   │   ├── Domain                 # The directory structure/naming here is up to you.
│   │   │   └── YourLogicClass.php
│   │   ├── Facade.php             # These are the 4 "gacela classes":
│   │   └── Factory.php            # - You can prefix them with its module name.
│   │   ├── Config.php             # - Autowiring customizable in `gacela.php`.
│   │   └── DependencyProvider.php # - Suffix naming customizable in `gacela.php`.
│   │
│   └── ExampleModuleWithPrefix
│       ├── Domain
│       │   └── YourLogicClass.php
│       ├── ExampleModuleWithPrefixFacade.php
│       └── ExampleModuleWithPrefixFactory.php
│       ├── ExampleModuleWithPrefixConfig.php
│       └── ExampleModuleWithPrefixDependencyProvider.php
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
