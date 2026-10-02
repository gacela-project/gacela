# Working in a Gacela project

This project splits its code into modules with Gacela. Follow these rules when you add or change code. The full docs for the installed version are in `vendor/gacela-project/gacela/docs/`; read the page named in each section before doing something new.

## The shape of a module

A module is a directory whose classes share a namespace, entered through one class:

- **Facade** (`extends AbstractFacade`): the module's public API. Other modules call only this.
- **Factory** (`extends AbstractFactory`): builds the module's services. `create*()` methods.
- **Config** (`extends AbstractConfig`): reads configuration with typed getters.
- **Provider** (`extends AbstractProvider`): supplies what comes from outside the module, other modules' Facades included.

Pillars are found by name. In `App\Billing` they are `BillingFacade` (or `Facade`), `BillingFactory`, `BillingConfig`, `BillingProvider`. Do not give any other class one of those suffixes.

Create a module with the scaffolder, never by hand:

```bash
vendor/bin/gacela make:module App/Billing            # all four pillars
vendor/bin/gacela make:module App/Billing --minimal  # Facade and Factory only
vendor/bin/gacela make:file App/Billing Config       # add a pillar later
```

## The rules

1. **Cross a module boundary only through its Facade.** Never `use` another module's Factory, domain class or repository. If you need something the Facade does not offer, add a Facade method in that module.
2. **Facade methods delegate.** A Facade method calls `$this->getFactory()->create...()->...` and nothing more. Logic lives in the module's domain classes.
3. **Build services in the Factory.** No `new` of a collaborator inside a domain class or a Facade.
4. **Get another module's Facade in a Factory** through the Provider: declare it with `#[Provides(CustomerFacade::class)]`, read it with `getProvidedDependency(CustomerFacade::class)`. The class name as the key is typed, so no `@var` above the call.
5. **Get a pillar outside the pillars** (a Controller, a Command) with `ServiceResolverAwareTrait` and `#[ServiceMap]`, not with a `@method` docblock.
6. **Read configuration in the module's Config**, through its typed getters (`getString()`, `getInt()`, ...). Expose intention-revealing methods; do not pass raw arrays around.
7. **Register infrastructure in `gacela.php`**, not inside modules: `addBinding()` for an interface, `addPluginStack()` for several implementations of one interface, `addHandlerRegistry()` for lookup by key, `tag()` for an untyped group. A class can also join a declared plugin stack with `#[Plugin(Contract::class)]`, or a tag with `#[Tag('name')]`, and a public method can listen to a module event with `#[AsListener]`.

Which way to get a dependency, case by case: `docs/getting-a-dependency.md`.

```php
final class BillingProvider extends AbstractProvider
{
    #[Provides(CustomerFacade::class)]
    public function customerFacade(): CustomerFacade
    {
        return new CustomerFacade();
    }
}

final class BillingFactory extends AbstractFactory
{
    public function createInvoiceIssuer(): InvoiceIssuer
    {
        return new InvoiceIssuer($this->getProvidedDependency(CustomerFacade::class));
    }
}
```

## Check your work

Run these before you say a change is done. Each answers "is this right?" in one call.

```bash
vendor/bin/gacela doctor --only-problems   # wiring, config, cache and module health; exit 1 on errors
vendor/bin/gacela debug:graph --check      # fails on a module dependency cycle
vendor/bin/gacela list:modules --json      # every module and its pillars, machine-readable
vendor/bin/gacela debug:module App/Billing # one module: resolved pillars, provided ids, public API
vendor/bin/gacela debug:plugins            # plugin stacks, tags and #[AsListener] methods, with where each is declared
vendor/bin/gacela debug:events --listened  # which events something listens to, #[AsListener] methods included
```

Static analysis carries the rules. The project's `phpstan.neon` should include:

```neon
includes:
    - vendor/gacela-project/gacela/phpstan-gacela.neon
```

That reports a Facade method that does more than delegate, a pillar that does not extend its base, and a Factory that reaches a Facade directly. The boundary rule, an import of another module's class that skips its Facade, is opt-in because it needs the namespace the modules live under:

```neon
services:
    -
        class: Gacela\PHPStan\Rules\CrossModuleViaFacadeRule
        tags: [phpstan.rules.rule]
        arguments:
            rootNamespace: App      # the namespace your modules sit under
            modulePathSegments: 1
```

If the project has it, keep it green. Do not silence these reports; fix the code. All options: `docs/module-boundaries.md` and `docs/static-analysis.md`.

## Tests

Extend `Gacela\Framework\Testing\GacelaTestCase`. To test one module with its neighbours replaced:

```php
$this->bootstrapModule(__DIR__, BillingFacade::class, doubles: [
    CustomerFacade::class => $this->createStub(CustomerFacade::class),
]);
```

See `docs/testing.md`.

## Attributes

Attributes register a class or method where it lives, with no line in `gacela.php`. They are found by scanning the module paths inside `projectNamespaces`, and `doctor` checks them.

- `#[Plugin(Contract::class, priority: 10)]` on a class joins a plugin stack. The stack must still be declared in `gacela.php`, empty if the attributes fill it: `addPluginStack(Contract::class, [])`.
- `#[Tag('name')]` on a class joins a tag. No declaration needed.
- `#[AsListener]` on a public method of a concrete class listens to the event its first parameter types. It hears events a module dispatches through `getProvidedDependency(EventDispatcherInterface::class)`, not Gacela's own.
- `#[Provides(Factory::ID)]` on a Provider method declares a provided dependency.
- `#[ServiceMap(method: 'getFacade', className: BillingFacade::class)]` on a class using `ServiceResolverAwareTrait` types its pillar accessor.

After adding one, check it with `vendor/bin/gacela debug:plugins` or `debug:events`.

## Long-running workers

Under FrankenPHP worker mode, Laravel Octane or RoadRunner, call `Gacela::resetRequestState()` after each request: it drops what the request built and keeps the warm caches. The Symfony bundle and the Laravel bridge call it for you. A service that holds request data belongs in a module's Provider, not as a singleton in `gacela.php`, which lives for the process. See `docs/long-running-runtimes.md`.

## Caches

With the file cache on, a new module or a new `#[Plugin]`, `#[Tag]` or `#[AsListener]` needs `vendor/bin/gacela cache:clear`. If something you just added is not found, clear the cache before you debug.

## All commands

Run `vendor/bin/gacela <command> --help` for options.

| Command | What it does |
|---|---|
| `agents:install` | Point the project's AGENTS.md at the Gacela guide for coding agents |
| `cache:clear` | Clear all Gacela cache files |
| `cache:warm` | Pre-resolve all module classes and warm the cache for production |
| `debug:config` | Show the effective merged configuration |
| `debug:container` | Display container debugging information (user bindings and plugins only) |
| `debug:dependencies` | Show the constructor parameters of a class and their resolvability through the container |
| `debug:events` | List every Gacela and project event, which have listeners, and which are on the hot path |
| `debug:graph` | Show the module dependency graph (which module imports which) |
| `debug:module` | Inspect a module: resolved gacela classes, container bindings, and dependency tree |
| `debug:modules` | Show dependency resolvability of every Gacela module pillar (Facade, Factory, Config, Provider) |
| `debug:plugins` | List plugin stack members, tags and #[AsListener] methods, with where each is declared |
| `debug:provides` | Find which Provider declares an id with #[Provides] |
| `doctor` | Run environmental & wiring health checks for the current Gacela setup |
| `dto:generate` | Generate the immutable DTO classes declared with declareDtoSchema() |
| `ide:meta` | Generate editor metadata for getProvidedDependency() from the #[Provides] attributes |
| `init` | Create a gacela.php config file in the project root |
| `list:modules` | Render all modules found |
| `make:file` | Generate a Facade, Factory, Config, Provider |
| `make:module` | Generate a basic module with an empty Facade, Factory, Config, Provider |
| `migrate:service-map` | Declare every @method pillar accessor with #[ServiceMap], for 3.0 |
| `profile:report` | Display performance profiling report |
| `stubs:publish` | Copy the scaffolder's templates into the project, so make:module generates your house style |
| `validate:config` | Validate Gacela configuration for errors and best practices |
