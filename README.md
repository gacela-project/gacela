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

Gacela is a class resolver. It helps you to improve the design of your modules.

> Inspired by Spryker Framework: https://github.com/spryker

Splitting your project into different modules will help the design of your project in terms of maintainability and
scalability. It will certainly encourage your modules to interact with each other in a unified way by following these
rules:

- Different modules can interact **only** via their `Facade`.
- The [`Facade`](https://gacela-project.com/docs/facade/) has access to the `Factory`.
- The [`Factory`](https://gacela-project.com/docs/factory/) creates the objects from the module and has access to the Module's `Config`.
- The [`Config`](https://gacela-project.com/docs/config/) gets the values from your defined config files.
- The [`DependencyProvider`](https://gacela-project.com/docs/dependency-provider/) uses the Locator to get the Facades from different modules.

### Installation

```bash
composer require gacela-project/gacela
```

### Documentation

You can check the full documentation in the official [website](https://gacela-project.com/).

### Examples

You can see an example of a module using gacela in [this repository](https://github.com/gacela-project/gacela-example).

### Contribute

You are more than welcome to contribute reporting issues, sharing ideas, or contributing with your Pull Requests.
