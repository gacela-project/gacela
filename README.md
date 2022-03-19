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
  <a href="https://github.com/gacela-project/gacela/master/LICENSE">
    <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="MIT Software License">
  </a>
</p>

## Gacela Framework

Gacela helps you to build modular applications.

> Inspired by Spryker Framework: https://github.com/spryker

Splitting your project into different modules help in terms of maintainability and scalability.
It encourages your modules to interact with each other in a unified way by following these rules:

- Different modules can interact **only** via their `Facade`.
- The [`Facade`](https://gacela-project.com/docs/facade/) has access to the `Factory`.
- The [`Factory`](https://gacela-project.com/docs/factory/) creates the objects from the module and has access to the Module's `Config`.
- The [`Config`](https://gacela-project.com/docs/config/) gets the values from your defined config files.
- The [`DependencyProvider`](https://gacela-project.com/docs/dependency-provider/) uses the Locator to get the Facades from different modules.

### Module structure

You can prefix gacela classes with the module name to improve readability. See more [about gacela](https://gacela-project.com/about-gacela/).

An example of an application structure using gacela modules:

```bash
application-name
├── gacela.php                     # You can customize some behaviours of gacela. 
│
├── config                         # Default config behaviour. Changeable in `gacela.php`.
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
│   │   ├── Config.php             # - They have autowiring. Changeable in `gacela.php`.
│   │   └── DependencyProvider.php # - You can customize the suffix naming. Changeable in `gacela.php`.
│
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

### Installation

```bash
composer require gacela-project/gacela
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
