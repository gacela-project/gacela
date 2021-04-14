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
</p>

## Gacela Framework

This framework helps you to improve the design of your modules.

> Inspired by Spryker Framework: https://github.com/spryker

Splitting your project into different modules will help the design of your project in terms of maintainability and
scalability. It will certainly encourage your modules to interact with each other in a unified way by following these
rules:

- Different modules can interact **ONLY** via their `Facade`
- The `Facade` is the **ONLY** one who has access to the `Factory`
- The `Factory` creates the objects from the module and has access to the Module's `Config`
- The `Config` can get the values from the `/config.php` file at the root of the project
- The `DependencyProvider` uses the Locator to get the Facades from different modules

### Installation

```bash
composer require gacela-project/gacela
```

## Documentation

- [Basic concepts](docs/001_basic_concepts.md): What are the characteristics of a module?
- [Facade](docs/002_facade.md): The entry point of your module
- [Factory](docs/003_factory.md): The place where you create your domain services and objects
- [Config](docs/004_config.md): Reads the `config` key-values
- [DependencyProvider](docs/005_dependency_provider.md): It defines the dependencies between modules

### Examples

You can see an example of some modules under the `tests/Integration` folder.

### Contribute

You are more than welcome to contribute reporting issues, sharing ideas, or contributing with your Pull Requests.
