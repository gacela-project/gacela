<p align="center">
  <a href="https://github.com/Chemaclass/gacela/actions">
    <img src="https://github.com/Chemaclass/gacela/workflows/CI/badge.svg" alt="GitHub Build Status">
  </a>
  <a href="https://scrutinizer-ci.com/g/Chemaclass/gacela/?branch=master">
    <img src="https://scrutinizer-ci.com/g/Chemaclass/gacela/badges/quality-score.png?b=master" alt="Scrutinizer Code Quality">
  </a>
  <a href="https://scrutinizer-ci.com/g/Chemaclass/gacela/?branch=master">
    <img src="https://scrutinizer-ci.com/g/Chemaclass/gacela/badges/coverage.png?b=master" alt="Scrutinizer Code Coverage">
  </a>
  <a href="https://shepherd.dev/github/Chemaclass/gacela">
    <img src="https://shepherd.dev/github/Chemaclass/gacela/coverage.svg" alt="Psalm Type-coverage Status">
  </a>
</p>

## Gacela Framework

This framework helps you to improve the design of your modules.

> Inspired by Spryker Framework: https://github.com/spryker

### What is the goal of this?

Splitting your project into different modules will help the design of your project in terms of maintainability and
scalability. It will certainly encourage your modules to interact with each other in a unified way by following these
rules:

- Different modules can interact **ONLY** via their `Facade`.
- The `Facade` has access **ONLY** to the `Factory`.
- The `Factory` creates the objects from that module and has access to the Module's `Config`.
- The `Config` can get the values from the `/config.php` file at the root of the project.

## Documentation

- [How to start](documentation/001_basic_concepts.md)
- How to start with these?
    - [Facade](documentation/002_facade.md)
    - [Factory](documentation/003_factory.md)
    - [DependencyProvider](documentation/004_dependency_provider.md)
    - [Config](documentation/005_config.md)

### Examples

You can see an example of some modules under the tests folder. In `/tests/Fixtures/`, you will find:

- `ExampleModuleCalculator`: an example of an independent module without interaction with other modules.
- `ExampleModuleGreeting`: an example of a module which interact with another module.

You can see how they work by looking inside the `/tests/Integration/` directory.
