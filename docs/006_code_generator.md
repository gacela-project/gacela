[Back to the index](../docs)

# Code Generator

Gacela Framework provides you some commands out-of-the-box to generate a `facade`, `factory`, `config`,
`dependency provider` or a full `module` with a single command.

- `make:module <target-namespace>`: Create a new Facade, Factory, Config, and DependencyProvider
- `make:facade <target-namespace>`: Create a new Facade
- `make:factory <target-namespace>`: Create a new Factory
- `make:config <target-namespace>`: Create a new Config
- `make:dependency-provider <target-namespace>`: Create a new DependencyProvider


Example:
`./vendor/bin/gacela make:module App/TestModule`

[<< Dependency Provider](../docs/005_dependency_provider.md)
