# Getting a dependency

Gacela supports many ways to obtain a dependency. This page names **one primary path per intent** — the one the docs teach, the one that is type-safe, and the one to reach for when you have no specific reason to do otherwise.

The rest still work. They are listed at the bottom with the situations where they are genuinely the right answer, because "supported" is not "deprecated".

| I want to… | Use |
|---|---|
| reach another module | from an entry point: `ServiceResolverAwareTrait` + `#[ServiceMap]` + `$this->getFacade()`. From a Factory: `#[Provides]` + `getProvidedDependency()` |
| get a collaborator inside my own module | a `create*()` method on the Factory, or `make()` when autowiring pays |
| get an external / infrastructure service | `#[Provides]` in the Provider, or `addBinding()` for an interface |
| collect several implementations | `addPluginStack()` when they share an interface, `tag()` when the set is unkeyed and untyped, `addHandlerRegistry()` when you look one up by key |
| read a config value | the typed getters on your `Config` |

## Reach another module

From an **entry-point class** — a Command, a Controller, anything outside the four pillars — `use ServiceResolverAwareTrait` and declare the pillar with `#[ServiceMap]`:

```php
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

#[ServiceMap(method: 'getFacade', className: BillingFacade::class)]
final class SendInvoiceController
{
    use ServiceResolverAwareTrait;

    public function __invoke(): void
    {
        $this->getFacade()->sendInvoice();
    }
}
```

The trait is what supplies the `__call()` that reads the attribute. Without it `$this->getFacade()` is a plain undefined method, attribute or no attribute.

**From inside a Factory, do not do this.** A Factory reaches another module through its own **Provider**, not by calling `getFacade()` on itself:

```php
// Provider
public const BILLING_FACADE = 'BILLING_FACADE';

#[Provides(self::BILLING_FACADE)]
public function billingFacade(Container $container): BillingFacade
{
    return $container->getLocator()->get(BillingFacade::class);
}

// Factory
public function createInvoiceSender(): InvoiceSender
{
    return new InvoiceSender($this->getProvidedDependency(Provider::BILLING_FACADE));
}
```

`FactoryDoesNotCallFacadeRule` enforces that, and it is registered by default in `phpstan-gacela.neon`: *"Factory must not call `$this->getFacade()`; same-module access goes through the Factory itself, cross-module access goes through the Provider."*

**Why declare the pillar.** The accessor works without the attribute — the runtime falls back to reading a `@method` docblock, and then to scanning your file's `use` statements, both of which now raise a deprecation. But an undeclared accessor is also untyped: 2.0 reports it, and before that it evaluated to `mixed`, which switched off checking of everything reached *through* it. The attribute alone types the accessor under both PHPStan and Psalm; a `@method` docblock adds IDE completion only, because PhpStorm reads `@method` and not `#[ServiceMap]`. Keeping one means writing the same fact twice — see [typed pillar accessors](static-analysis.md#typed-pillar-accessors) for the drift cost.

The `getProvidedDependency()` side of this needs no docblock at all: `ide:meta` writes editor metadata for it, described under [IDE metadata](static-analysis.md#ide-metadata).

Cross-module access goes through the other module's **Facade**, never its Factory or internals. `CrossModuleViaFacadeRule` enforces this if you enable it.

## Get a collaborator inside my own module

Write a `create*()` method on your Factory:

```php
final class Factory extends AbstractFactory
{
    public function createInvoiceSender(): InvoiceSender
    {
        return new InvoiceSender($this->createPdfRenderer());
    }
}
```

When the wiring is pure type-based plumbing, `make()` autowires it through the module container instead — honouring `#[Inject]`, `#[Singleton]` and `#[Factory]`, and needing no Provider at all:

```php
public function createInvoiceSender(): InvoiceSender
{
    return $this->make(InvoiceSender::class);
}
```

Use `create*()` when construction has decisions in it, `make()` when it does not. A Facade reaches its own Factory with `$this->getFactory()`, which is a real typed method — no attribute required.

## Get an external / infrastructure service

Declare it in the module's Provider with `#[Provides]`:

```php
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;

final class Provider extends AbstractProvider
{
    #[Provides(PaymentGateway::class)]
    public function paymentGateway(): PaymentGateway
    {
        return new StripeGateway();
    }
}
```

Read it back in the Factory with the class-string form, which is typed:

```php
$gateway = $this->getProvidedDependency(PaymentGateway::class);
```

For "this interface means that implementation" across the whole app, use `addBinding()` in `gacela.php` instead — the container then autowires it everywhere, including through `make()`:

```php
$config->addBinding(PaymentGateway::class, StripeGateway::class);
```

### What a miss looks like

`getProvidedDependency()` **returns `null` for an id nothing provides** — it does not throw. Measured across the shapes you can pass it:

| what you pass | you get |
|---|---|
| an id a Provider declares | the value |
| a **misspelled** string id | `null` |
| an unregistered **concrete** class | an autowired instance |
| an unregistered **interface** | `null` |
| a class name that does not exist | `null` |

The concrete-class row is why it cannot simply throw: the container autowires a class it can construct, which is the behaviour `make()` relies on. But it means a typo in a string id is not reported anywhere — the `null` travels until something calls a method on it, and the stack points at the consumer rather than at the id.

Two habits cost nothing and remove the guesswork.

Declare ids as **constants on the Provider** (`Provider::BILLING_FACADE`, not `'billing.facade'`), so a typo is a fatal undefined-constant at the call site instead of a `null` that travels.

Declare the dependency with **`#[Provides]`**, which is what puts the id in `vendor/bin/gacela debug:module App/Blog`. That command lists the ids the attribute declares and not the ones a Provider registers imperatively with `$container->set()` — its heading says `Provides (#[Provides])` for exactly that reason. A module wired the imperative way prints `(none)` there however many ids it registers, so the attribute is what makes an id visible to tooling at all.

This is the same shape as an [unanswerable tagged id](cli.md), which `doctor` reports — a group iterating one hole, failing on the consumer. Nothing reports this one, because an id may legitimately come from another module's Provider, from `addBinding()`, or from `extendService()`, so "no Provider declares it" is not the same as "it is wrong".

## Collect several implementations

Two shapes, and which one you want is decided by a single question: **do you look a member up, or do you use all of them?**

### Unkeyed — every implementation of something

A set of validators, listeners or rules, where the consumer iterates the whole collection. Group them with `tag()` in `gacela.php`:

```php
$config->tag([NotEmptyValidator::class, EmailValidator::class], 'validators');
```

Resolve the group with `tagged()`, which instantiates each id lazily, in the order it was tagged. A Provider is where you turn it into a provided dependency:

```php
final class Provider extends AbstractProvider
{
    public const VALIDATORS = 'CHECKOUT_VALIDATORS';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(
            self::VALIDATORS,
            static fn (): array => [...$container->tagged('validators')],
        );
    }
}
```

A tag declared in `gacela.php` reaches **every** module's container, so a module can consume a tag it did not declare — which is what makes a tag an extension point rather than just a list.

A module that wants to *add* to a tag calls `Container::tag()` in its own Provider:

```php
public function provideModuleDependencies(Container $container): void
{
    $container->tag(CardValidator::class, 'validators');
    // tagged('validators') here now yields the app-wide ids plus CardValidator
}
```

That contribution stays in **that module's** container. Two modules tagging under the same label do not collide and do not see each other's additions; each sees the app-wide set plus its own. That is deliberate — module containers are separate, and a tag is not a back channel between modules.

### Keyed — the one implementation for this key

A command bus, a message dispatcher, anything that picks a handler by a business key. Use `addHandlerRegistry()`:

```php
$config->addHandlerRegistry(HandlerRegistry::class, [
    'email' => EmailHandler::class,
    'sms' => SmsHandler::class,
]);
```

```php
$registry = $this->getProvidedDependency(HandlerRegistry::class);
$handler = $registry->get('email');
```

Both resolve their members through the container and both instantiate lazily. They are not two ways to do one thing: a registry answers *"the handler for this key"* and throws on an unknown one, while a tag answers *"all of these"* and has no notion of a key to miss.

### Typed — every implementation of one interface

An extension point: several implementations behind one interface, iterated in order by a module that does not know who contributed them. Declare it with `addPluginStack()`:

```php
$config->addPluginStack(InvoiceDecorator::class, [
    AddVatBreakdownDecorator::class,
    AddPaymentTermsDecorator::class,
]);
```

Consume it from the Factory, typed — the `foreach` in the consumer knows what it is iterating:

```php
final class InvoiceFactory extends AbstractFactory
{
    public function createRenderer(): InvoiceRenderer
    {
        return new InvoiceRenderer($this->getPluginStack(InvoiceDecorator::class));
    }
}
```

Calling `addPluginStack()` again for the same interface **appends**, seed first, in the order the config sources run — so another package's `extendGacelaConfig` or an environment override contributes to a stack it did not declare, and there is no second verb to choose between. A class registered twice appears once.

Entries resolve through the container on first read and are kept, so iterating twice yields the same plugins however they are bound. A class that does not implement the interface fails on that first read, naming the class and the stack, rather than as a `TypeError` somewhere inside the consumer's loop.

**Which of the three.** A registry answers *the one implementation for this key*. A tag answers *all of these*, untyped, contributed from anywhere. A stack answers *all implementations of this interface*, in order, typed and checked. The contract is what separates a stack from a tag — without one, the answer is a tag.

A stack is reached with `getPluginStack()` rather than `getProvidedDependency()` deliberately: the latter means *the thing registered under this id*, and both analysers type it as the class the id names, so routing a stack through it would make one id mean two things.

## Read a config value

The typed getters are `protected`, so they are used *inside* your `Config` class, which exposes intention-revealing methods to the rest of the module:

```php
final class Config extends AbstractConfig
{
    public function retryAttempts(): int
    {
        return $this->getInt('billing.retry-attempts', 3);
    }
}
```

`getString()`, `getInt()`, `getFloat()`, `getBool()`, `getArray()` and the untyped `get()` are all available. This is the shape the other three intents are aiming for: one path, typed variants, impossible to use wrongly.

## Resolve a kind of my own

Gacela resolves four kinds by suffix — Facade, Factory, Config, Provider. A project that wants a fifth declares it:

```php
// gacela.php
$config->addResolvableType('Exporter', AbstractExporter::class, ['Exporter', 'Feed']);
```

From then on the kind behaves like a pillar. The finder tries `Report\ReportExporter`, then `Report\Exporter`, then the same two shapes for `Feed`, through the same namespaces and rules; the resolver cache holds it; `doctor` knows its suffixes. Suffixes default to the kind's own name, so `addResolvableType('Exporter', AbstractExporter::class)` is the common case.

Reach the resolved class from anywhere the pillars are reachable:

```php
use Gacela\Framework\DeclaredTypeResolverAwareTrait;

final class ReportFactory extends AbstractFactory
{
    use DeclaredTypeResolverAwareTrait;

    public function createExportedReport(): string
    {
        return $this->getResolvedType('Exporter')->export();
    }
}
```

A project that would rather write `getExporter()` puts one method over that call — which is exactly what the pillars' own accessors are.

Two rules the declaration enforces at bootstrap, so neither surfaces later as a resolution that quietly went the wrong way:

- **A suffix belongs to one kind.** Declaring a suffix another kind already claims is refused. Longest suffix wins, so a declared `ServiceProvider` beats the built-in `Provider` it ends with.
- **The base must exist.** A kind naming a class or interface that does not exist is refused, rather than accepted and never satisfied.

The abstract is a declared contract, not a scan: Gacela never searches your tree for classes extending it. To have the analysers hold a declared kind to its base, register one more `SuffixExtendsRule` in your own `phpstan.neon` — see [static analysis](static-analysis.md#typed-pillar-accessors).

`addSuffixTypeFacade()` and its three siblings are now sugar over this call, and stay supported: they widen a pillar rather than declare a kind.

## Vary the wiring per entrypoint

One module often serves several entrypoints — a web request, a queue worker, a scheduled job — and the wiring differs: the worker wants a batching writer, the request a synchronous one.

The tempting answer is a branch inside the Factory:

```php
public function createProductWriter(): ProductWriter
{
    return $this->getConfig()->isWorker()      // don't
        ? new BatchingProductWriter()
        : new SyncProductWriter();
}
```

That hides the variant in control flow, where `debug:module` cannot show it and `cache:warm` cannot precompute past it.

Each entrypoint already has its own bootstrap, so give it its own **project namespaces** instead. Configured namespaces are searched before the module's own, which is tried last:

```php
// bin/worker.php
Gacela::bootstrap(dirname(__DIR__), static function (GacelaConfig $config): void {
    $config->setProjectNamespaces(['App\Worker']);
});
```

```
src/
├── Catalog/
│   ├── CatalogFacade.php
│   ├── CatalogFactory.php        synchronous writer
│   └── Domain/ProductWriter.php
└── Worker/
    └── Catalog/
        └── CatalogFactory.php    batching writer
```

`App\Worker\Catalog\CatalogFactory` now answers for `App\Catalog\CatalogFacade` under the worker bootstrap, and nothing changes for the web entrypoint, which sets no project namespaces at all.

Four things worth knowing:

- **You override only what differs.** The variant class extends the original and overrides the one method that changes; every other `create*()` still comes from the base Factory. A parallel tree of *whole* modules is not what this asks for.
- **List order is resolution order.** `setProjectNamespaces(['App\Worker', 'App\Shared'])` tries `App\Worker` first, then `App\Shared`, then the module's own namespace. The first candidate that exists wins.
- **It covers every pillar Gacela *resolves*** — Facade, Factory, Config and Provider, plus any [kind you declared](#resolve-a-kind-of-my-own). A Facade you reach with `new CatalogFacade()` is not resolved, so that call is untouched; one reached through `getFacade()` is.
- **Each bootstrap gets its own cache file.** The on-disk class-name cache is keyed by a hash of `projectNamespaces` and suffix types, so a warm web cache can never answer for the worker — see [caching](caching.md). At deploy, run `cache:warm` once per entrypoint; see [production performance](production-performance.md).

The honest limit: the variant lives *outside* the module folder, so the module no longer tells its own whole story. [#679](https://github.com/gacela-project/gacela/issues/679) tracks letting the variant live inside the module instead, and is waiting on a real application that finds the parallel tree painful enough to justify the cost.

## The other paths, and when they are right

These are supported and are **not** deprecated. They are simply not the answer to "how do I get a dependency?" — reach for them when the situation below is actually yours.

| Path | Reach for it when |
|---|---|
| `addBindingIf()` | shipping a plugin default an application may override |
| `addFactory('id', fn)` | you need a **new instance per resolution**, not a shared one |
| `addProtected('id', fn)` | the value *is* a closure and must not be invoked |
| `addAlias('short', Full::class)` | a long id needs a short name |
| `addLazy()` | construction is expensive and often unused |
| `extendService('id', fn)` | decorating a service registered elsewhere, **wherever** that id is registered — *replacing* what comes out |
| `extendProviderService(Provider::class, 'id', fn)` | decorating that id **only as one Provider registers it** — when two modules reuse an un-namespaced key, or when one module decorates a sibling's binding without shadowing the sibling's whole Provider |
| `afterResolving('id', fn)` | touching an instance after it is built without rebuilding it, e.g. a setter on every implementation of an interface |
| `when(X)->needs(Y)->give(Z)` | one consumer needs a different implementation than everyone else |
| `loadDefinitions([...])` / `loadDefinitions('file.json')` | the wiring is generated, shared between environments, or reviewed as a diff — see [container-configuration](container-configuration.md#definitions-as-data) |
| `addExternalService()` | handing a framework object (Symfony kernel, PSR container) to Gacela at bootstrap |
| `$container->get('id')` inside a Provider | wiring one provided service from another |
| `#[Singleton]` / `#[Factory]` on a class | declaring **lifetime**, which is a different question from lookup |

### Why none of them were removed

The 2.0 inventory ([RFC-0002](rfc/0002-dependency-paths-inventory.md)) counted 25 paths and the obvious conclusion was "delete most of them". That would have been the wrong lesson.

The problem in the other intents was never the count — it was unrelated mechanisms competing for the same job with no indication which one to use. That reasoning is now the framework's whole policy on public surface, written down with its gate in [RFC-0003: the bootstrap configuration surface](rfc/0003-bootstrap-configuration-surface.md).

Naming a primary path fixes that. Deleting working escape hatches would only have broken applications that had a good reason to use one.
