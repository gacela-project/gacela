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
4. **Get another module's Facade in a Factory** through the Provider: declare it with `#[Provides]`, read it with `getProvidedDependency()`.
5. **Get a pillar outside the pillars** (a Controller, a Command) with `ServiceResolverAwareTrait` and `#[ServiceMap]`, not with a `@method` docblock.
6. **Read configuration in the module's Config**, through its typed getters (`getString()`, `getInt()`, ...). Expose intention-revealing methods; do not pass raw arrays around.
7. **Register infrastructure in `gacela.php`**, not inside modules: `addBinding()` for an interface, `addPluginStack()` for several implementations of one interface, `addHandlerRegistry()` for lookup by key, `tag()` for an untyped group. A class can also join a declared plugin stack with `#[Plugin(Contract::class)]`.

Which way to get a dependency, case by case: `docs/getting-a-dependency.md`.

```php
final class BillingProvider extends AbstractProvider
{
    #[Provides(BillingFactory::CUSTOMER_FACADE)]
    public function customerFacade(): CustomerFacade
    {
        return new CustomerFacade();
    }
}

final class BillingFactory extends AbstractFactory
{
    public const CUSTOMER_FACADE = 'CUSTOMER_FACADE';

    public function createInvoiceIssuer(): InvoiceIssuer
    {
        return new InvoiceIssuer($this->getProvidedDependency(self::CUSTOMER_FACADE));
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

## Caches

With the file cache on, a new module or a new `#[Plugin]` class needs `vendor/bin/gacela cache:clear`. If something you just added is not found, clear the cache before you debug.
