## Gacela Framework

This framework helps you to improve the design of your modules.

> Inspired by Spryker Framework: https://github.com/spryker

### What is the goal of this?

Splitting your project into different modules will help them in terms of maintainability and scalability.
Help your modules to interact with each other in a unified way by following these rules:

- Different modules can interact ONLY via their Facade.
- The Facade has access ONLY to the Factory.
- The Factory creates the objects from that module and has access to its own Config.
- The Config can get the values from the "config_default.php" file.

## Documentation

- [How to start](documentation/001_basic_concepts.md)
- How to start with these?
  - [Facade](documentation/002_facade.md)
  - [Factory](documentation/003_factory.md)
  - [DependencyProvider](documentation/004_dependency_provider.md)
  - [Config](documentation/005_config.md)
