# Getting a dependency

Gacela supports many ways to obtain a dependency. This page names **one primary
path per intent** — the one the docs teach, the one that is type-safe, and the
one to reach for when you have no specific reason to do otherwise.

The rest still work. They are listed at the bottom with the situations where
they are genuinely the right answer, because "supported" is not "deprecated".

| I want to… | Use |
|---|---|
| reach another module | from an entry point: `ServiceResolverAwareTrait` + `#[ServiceMap]` + `$this->getFacade()`. From a Factory: `#[Provides]` + `getProvidedDependency()` |
| get a collaborator inside my own module | a `create*()` method on the Factory, or `make()` when autowiring pays |
| get an external / infrastructure service | `#[Provides]` in the Provider, or `addBinding()` for an interface |
| collect several implementations | `tag()` when the set is unkeyed, `addHandlerRegistry()` when you look one up by key |
| read a config value | the typed getters on your `Config` |

## Reach another module

From an **entry-point class** — a Command, a Controller, anything outside the
four pillars — `use ServiceResolverAwareTrait` and declare the pillar with
`#[ServiceMap]`:

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

The trait is what supplies the `__call()` that reads the attribute. Without it
`$this->getFacade()` is a plain undefined method, attribute or no attribute.

**From inside a Factory, do not do this.** A Factory reaches another module
through its own **Provider**, not by calling `getFacade()` on itself:

```php
// Provider
#[Provides(self::BILLING_FACADE)]
public function billingFacade(): BillingFacade
{
    return $this->getLocator()->get(BillingFacade::class);
}

// Factory
public function createInvoiceSender(): InvoiceSender
{
    return new InvoiceSender($this->getProvidedDependency(Provider::BILLING_FACADE));
}
```

`FactoryDoesNotCallFacadeRule` enforces that, and it is registered by default in
`phpstan-gacela.neon`: *"Factory must not call `$this->getFacade()`; same-module
access goes through the Factory itself, cross-module access goes through the
Provider."*

**Why declare the pillar.** The accessor works without the attribute — the
runtime falls back to reading a `@method` docblock, and then to scanning your
file's `use` statements, both of which now raise a deprecation. But an
undeclared accessor is also untyped: 2.0 reports it, and before that it
evaluated to `mixed`, which switched off checking of everything reached
*through* it. A `@method` docblock is equally fine for typing; PHPStan reads it
natively, and it is still worth keeping alongside the attribute for IDEs.

Cross-module access goes through the other module's **Facade**, never its
Factory or internals. `CrossModuleViaFacadeRule` enforces this if you enable it.

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

When the wiring is pure type-based plumbing, `make()` autowires it through the
module container instead — honouring `#[Inject]`, `#[Singleton]` and
`#[Factory]`, and needing no Provider at all:

```php
public function createInvoiceSender(): InvoiceSender
{
    return $this->make(InvoiceSender::class);
}
```

Use `create*()` when construction has decisions in it, `make()` when it does
not. A Facade reaches its own Factory with `$this->getFactory()`, which is a
real typed method — no attribute required.

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

For "this interface means that implementation" across the whole app, use
`addBinding()` in `gacela.php` instead — the container then autowires it
everywhere, including through `make()`:

```php
$config->addBinding(PaymentGateway::class, StripeGateway::class);
```

## Collect several implementations

Two shapes, and which one you want is decided by a single question: **do you
look a member up, or do you use all of them?**

### Unkeyed — every implementation of something

A set of validators, listeners or rules, where the consumer iterates the whole
collection. Group them with `tag()` in `gacela.php`:

```php
$config->tag([NotEmptyValidator::class, EmailValidator::class], 'validators');
```

Resolve the group with `tagged()`, which instantiates each id lazily, in the
order it was tagged. A Provider is where you turn it into a provided dependency:

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

A tag declared in `gacela.php` reaches **every** module's container, so a module
can consume a tag it did not declare — which is what makes a tag an extension
point rather than just a list.

A module that wants to *add* to a tag calls `Container::tag()` in its own
Provider:

```php
public function provideModuleDependencies(Container $container): void
{
    $container->tag(CardValidator::class, 'validators');
    // tagged('validators') here now yields the app-wide ids plus CardValidator
}
```

That contribution stays in **that module's** container. Two modules tagging
under the same label do not collide and do not see each other's additions; each
sees the app-wide set plus its own. That is deliberate — module containers are
separate, and a tag is not a back channel between modules.

### Keyed — the one implementation for this key

A command bus, a message dispatcher, anything that picks a handler by a business
key. Use `addHandlerRegistry()`:

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

Both resolve their members through the container and both instantiate lazily.
They are not two ways to do one thing: a registry answers *"the handler for this
key"* and throws on an unknown one, while a tag answers *"all of these"* and has
no notion of a key to miss.

## Read a config value

The typed getters are `protected`, so they are used *inside* your `Config`
class, which exposes intention-revealing methods to the rest of the module:

```php
final class Config extends AbstractConfig
{
    public function retryAttempts(): int
    {
        return $this->getInt('billing.retry-attempts', 3);
    }
}
```

`getString()`, `getInt()`, `getFloat()`, `getBool()`, `getArray()` and the
untyped `get()` are all available. This is the shape the other three intents are
aiming for: one path, typed variants, impossible to use wrongly.

## The other paths, and when they are right

These are supported and are **not** deprecated. They are simply not the answer
to "how do I get a dependency?" — reach for them when the situation below is
actually yours.

| Path | Reach for it when |
|---|---|
| `addBindingIf()` | shipping a plugin default an application may override |
| `addFactory('id', fn)` | you need a **new instance per resolution**, not a shared one |
| `addProtected('id', fn)` | the value *is* a closure and must not be invoked |
| `addAlias('short', Full::class)` | a long id needs a short name |
| `addLazy()` | construction is expensive and often unused |
| `extendService('id', fn)` | decorating a service registered elsewhere — *replacing* what comes out |
| `afterResolving('id', fn)` | touching an instance after it is built without rebuilding it, e.g. a setter on every implementation of an interface |
| `when(X)->needs(Y)->give(Z)` | one consumer needs a different implementation than everyone else |
| `addExternalService()` | handing a framework object (Symfony kernel, PSR container) to Gacela at bootstrap |
| `$container->get('id')` inside a Provider | wiring one provided service from another |
| `#[Singleton]` / `#[Factory]` on a class | declaring **lifetime**, which is a different question from lookup |

### Why none of them were removed

The 2.0 inventory ([RFC-0002](rfc/0002-dependency-paths-inventory.md)) counted 25
paths and the obvious conclusion was "delete most of them". That would have been
the wrong lesson.

Reading a config value has **six** methods for one intent and nobody has ever
complained, because they are the same path with typed variants — discovered
together, impossible to confuse. The problem in the other intents was never the
count. It was that unrelated mechanisms competed for the same job with no
indication which one you were supposed to use.

Naming a primary path fixes that. Deleting working escape hatches would only
have broken applications that had a good reason to use one.
