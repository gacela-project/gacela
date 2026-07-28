# Container Configuration

Configure your dependency injection container in `gacela.php`.

## Factory Services

Create a new instance on each resolution (non-singleton).

```php
use Gacela\Framework\Bootstrap\GacelaConfig;

return static function (GacelaConfig $config): void {
    $config->addFactory('session', static fn() => new SessionHandler());
};
```

Every call to `$container->get('session')` returns a new instance.

## Protected Services

Store closures without invoking them.

```php
return static function (GacelaConfig $config): void {
    $config->addProtected('db.factory', static fn() => new Database());
};

// Later:
$factory = $container->get('db.factory'); // Returns the closure
$db = $factory(); // Invoke when needed
```

Protected services cannot be extended via `extendService()`.

## Post-construction Hooks

Run a callback against an instance the moment it is built, without rebuilding it.

```php
return static function (GacelaConfig $config): void {
    $config->afterResolving(
        LoggerAwareInterface::class,
        static fn (LoggerAwareInterface $service) => $service->setLogger($logger),
    );
};
```

**The id may name an interface**, which is the point: one registration covers every
implementation, because the match is made against the resolved instance rather than
by looking the requested id up in a map.

Hooks fire on container-level resolution — `get()`, `getOrFail()` and `make()` — in
registration order. A class the container autowires as a *nested* constructor
dependency is not resolved at this level, so hooks do not fire for it. A callback
that throws removes the instance rather than leaving a half-wired one for the next
caller, and a container with no hooks pays nothing per resolution.

Reach for `extendService()` instead when you need to *replace* what comes out, and
for a [`ServiceResolvedEvent`](events.md) listener when you only want to observe.

## Service Aliases

Reference the same service with different names.

```php
return static function (GacelaConfig $config): void {
    $config->addBinding(LoggerInterface::class, FileLogger::class);
    $config->addAlias('logger', LoggerInterface::class);
};

// Both resolve to the same instance:
$container->get(LoggerInterface::class);
$container->get('logger');
```

## Conditional Bindings

Register a binding only when the key is not already bound — a default that the
application (or an earlier binding) can override. Useful for plugins that want to
ship a sensible default without clobbering a host application's choice.

```php
return static function (GacelaConfig $config): void {
    // App opts into its own logger.
    $config->addBinding(LoggerInterface::class, JsonLogger::class);

    // A plugin provides a fallback; skipped because the key is already bound.
    $config->addBindingIf(LoggerInterface::class, NullLogger::class);
};

// Resolves to JsonLogger.
$container->get(LoggerInterface::class);
```

`addBindingIf()` compares against the bindings already declared in the same config.
If no binding exists for the key, it behaves exactly like `addBinding()`.

## Contextual Bindings

Provide different implementations based on which class is requesting a dependency.

```php
return static function (GacelaConfig $config): void {
    $config->when(UserController::class)
        ->needs(LoggerInterface::class)
        ->give(FileLogger::class);

    $config->when(AdminController::class)
        ->needs(LoggerInterface::class)
        ->give(DatabaseLogger::class);
};
```

When `UserController` requests `LoggerInterface`, it receives `FileLogger`. When `AdminController` requests the same interface, it receives `DatabaseLogger`.

You can also bind to multiple classes at once:

```php
$config->when([ApiController::class, WebController::class])
    ->needs(CacheInterface::class)
    ->give(RedisCache::class);
```

### Scalar parameters

`needs()` also accepts a constructor parameter name (prefixed with `$`), so a
class-specific scalar can be injected without a config lookup:

```php
$config->when(PaymentGateway::class)
    ->needs('$apiTimeout')
    ->give(30);
```

Any non-class value works: strings, numbers, booleans, arrays — or a closure
when the value should be built lazily.

## Constructor Injection with `#[Inject]`

The container autowires constructor parameters by type-hint. For most cases
that's all you need — declare the type, the container resolves it.

`#[Inject]` is the opt-in for the two cases autowiring alone can't express:
disambiguating an interface with multiple possible concretes, and marking a
parameter as explicitly container-owned for tooling like `debug:dependencies`.

```php
use Gacela\Container\Attribute\Inject;

final class CatalogService
{
    public function __construct(
        #[Inject] private readonly LoggerInterface $logger,
        #[Inject(RedisCache::class)] private readonly CacheInterface $cache,
    ) {}
}
```

- `#[Inject]` with no argument flags the parameter for the container — the
  type hint drives resolution.
- `#[Inject(RedisCache::class)]` routes this specific parameter to
  `RedisCache`, independent of any global `addBinding` for `CacheInterface`.

### On properties

`#[Inject]` also targets properties, for classes whose constructor is not yours
to change — a base class from a vendor package, or one whose signature is fixed
by a framework contract.

```php
final class CatalogController extends VendorController
{
    #[Inject]
    private LoggerInterface $logger;

    #[Inject(RedisCache::class)]
    private CacheInterface $cache;
}
```

Private, protected and inherited properties all work. Constructor injection
stays the default for everything else: a dependency in the signature is visible
to a reader, and to a plain `new` outside the container.

Three cases are rejected by name rather than by a raw PHP error, because none
of them can work:

- `readonly` — only writable from inside the declaring class. Promote it to a
  constructor parameter, which keeps it readonly.
- untyped or scalar-typed — there is nothing for the container to resolve.
- `static` — ignored entirely; state shared by every instance is not a
  dependency of any one of them.

A property promoted in the constructor is injected by the constructor, not
twice.

A cycle reached through an injected property still raises
`CircularDependencyException`: property injection runs inside the same
resolution stack, so it is not a way around the diagnostic.

### Resolution order

For `#[Inject($override)] Type $p` on a class `Consumer`, the container tries:

1. A runtime override passed to `make()` under `$p`'s name → use it
   (top-level parameters only).
2. `$config->when(Consumer)->needs('$p')->give(X)` (a **named** contextual
   binding, matched on the parameter name) → resolve `X`.
3. `#[Inject($override)]` set → resolve `$override`.
4. **`$p` has a default value → use the default.**
5. `$config->when(Consumer)->needs(Type)->give(X)` → resolve `X`.
6. `$config->addBinding(Type, X)` → resolve `X`.
7. `Type` is an instantiable class → `new Type(...)` with recursive autowire.
8. Otherwise → throw `DependencyNotFoundException`.

> **A default beats the type-based bindings below it.** Step 4 returns before
> the container looks at contextual bindings by type or at `addBinding()`. So
> `__construct(?Engine $engine = null)` resolves to `null` even with
> `addBinding(Engine::class, RealEngine::class)` registered — the binding is
> silently not applied. Leave the default off a parameter you expect the
> container to fill, or name it explicitly with `#[Inject]`, which is checked
> first.

Nullability changes nothing on its own: a `?Foo` with no default and no binding
is **not** resolved to `null`, it throws `DependencyNotFoundException` like any
other unresolvable parameter. Only a default produces `null`.

### Interactions

- Contextual bindings win over global `addBinding` — by name at step 2, by type at step 5, both before `addBinding` at step 6.
- Protected services (`addProtected`) cannot be injected — they're stored
  as raw closures and the container won't instantiate them.
- `#[Inject]` does not replace `#[ServiceMap]` or `ServiceResolverAwareTrait`
  — those serve a different `__call`-based dispatch use case and remain
  supported.

### Visibility in tooling

`vendor/bin/gacela debug:dependencies <Class>` lists every constructor parameter
with its resolution kind. `#[Inject]` parameters show up tagged `inject`,
with the override concrete rendered inline when present:

```
✓ $logger LoggerInterface (inject)
✓ $cache CacheInterface (inject -> App\Cache\RedisCache)
```

That view stops at the constructor, and it is derived from type hints. Add
`--tree` to append the **transitive** dependencies, taken from the container's
own resolution view rather than re-derived — so bindings and contextual
bindings are already applied, and a bound interface is listed as the concrete
that will actually be built:

```
bin/gacela debug:dependencies "App\Catalog\CatalogService" --tree
```

```
Dependency tree for App\Catalog\CatalogService
============================================================

  ✓ App\Catalog\ProductRepository (autowired)
  ✓ App\Cache\RedisCache (binding)
  ✓ Psr\Log\LoggerInterface (instance)
  ✗ App\Search\IndexerInterface (unresolvable)

Dependencies: 4
```

Each node reports how the container will supply it:

| Marker | Meaning |
|---|---|
| `binding` | an explicit binding is registered for the id |
| `instance` | the container already holds an instance, or a singleton it resolved |
| `autowired` | nothing registered, but the class will be constructed on demand |
| `unresolvable` | the container owns nothing and the class cannot be built |

The distinction comes from `Container::provides()`, which asks whether the
container *owns* something for an id. That is narrower than `has()`, which is
also true of anything merely autowirable — and it is the difference the
one-level view could not express, because an interface with a binding read the
same as one without.

An unresolvable node is printed, not thrown: the command stays a diagnostic.

### Migration from `ServiceResolverAwareTrait`

Before:

```php
final class PhelRunCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @psalm-suppress InternalMethod */
        $this->getFacade()->clearCache();
        return self::SUCCESS;
    }
}
```

After:

```php
use Gacela\Container\Attribute\Inject;

final class PhelRunCommand extends Command
{
    public function __construct(
        #[Inject] private readonly PhelFacadeInterface $phel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->phel->clearCache();
        return self::SUCCESS;
    }
}
```

Trait gone. `@psalm-suppress` gone. Dependency visible to tooling.

### Symfony `Command` classes

Symfony `Command` constructors are autowired by Symfony's own container.
`#[Inject]` on a Symfony-managed class does not take effect on its own — a
compiler pass is required to route `#[Inject]` parameters to Gacela before
Symfony's autowire claims them. A dedicated `gacela/symfony-bridge` package
ships this pass; adopt it in projects where Symfony owns the container.

## Class Attributes: `#[Singleton]`, `#[Factory]` and `#[Lazy]`

Instead of registering a binding or an `addFactory()` closure, module authors can
annotate the service class itself. Any class resolved through the container —
including via `getProvidedDependency()` in a Gacela `Factory` — honors these:

```php
use Gacela\Container\Attribute\Factory;
use Gacela\Container\Attribute\Singleton;

#[Singleton]
final class ConnectionPool {}   // one instance, cached and reused

#[Factory]
final class ReportBuilder {}    // fresh instance on every resolution
```

```php
final class MyModuleFactory extends AbstractFactory
{
    public function createPool(): ConnectionPool
    {
        /** @var ConnectionPool */
        return $this->getProvidedDependency(ConnectionPool::class);  // same instance every call
    }
}
```

The attribute lives with the class, so the lifetime choice travels with the code
instead of a `gacela.php` entry. Equivalent imperative registrations:

| Attribute | Imperative equivalent |
|---|---|
| `#[Singleton]` on `Pool` | `$container->set(Pool::class, new Pool())` in a provider |
| `#[Factory]` on `Builder` | `$config->addFactory(Builder::class, static fn() => new Builder())` |
| `#[Inject(X::class)]` on a param | `$config->when(Consumer::class)->needs(Type::class)->give(X::class)` |
| `#[Lazy]` on `Report` | `$config->addLazy(Report::class, static fn() => new Report())`, or `$container->lazy(Report::class)` in a provider for a class you cannot annotate |

Notes:

- `#[Singleton]` instances are cached per container. Each Gacela module factory
  keeps one container, so repeated resolves through the same module share the
  instance.
- Without any attribute, an unregistered class is autowired fresh on each
  `get()` — `#[Singleton]` is the opt-in for caching, `#[Factory]` documents
  the fresh-instance intent explicitly.
- Constructor params of attribute-annotated classes still go through the normal
  resolution order (bindings, contextual bindings, `#[Inject]`).
- `#[Lazy]` returns a real instance of the class whose constructor has not run
  yet; touching any property or method initializes it. Useful for an expensive
  service a given request may never reach. **Requires PHP 8.4** for native lazy
  objects — on 8.3 the class is constructed eagerly instead, which is
  unobservable apart from the timing, so it is safe to annotate either way.

### Resolving domain objects by type with `make()`

`AbstractFactory::make()` gives a `create*()` method a typed, autowiring
construction path through the same module container — so a factory can resolve a
domain object by type instead of hand-`new`ing it and wiring each argument:

```php
final class CheckoutFactory extends AbstractFactory
{
    public function createCheckoutService(): CheckoutService
    {
        // Constructor autowired; #[Inject]/#[Singleton]/#[Factory] honored.
        return $this->make(CheckoutService::class);
    }
}
```

- The return type is inferred from the class-string, so no `/** @var */` is
  needed at the call site (unlike `getProvidedDependency()`, which returns
  `mixed`).
- Pass runtime overrides by parameter name to override specific constructor
  arguments; the instance is then always built fresh:

  ```php
  $this->make(CheckoutService::class, ['currency' => 'EUR']);
  ```

  Scalars/config are best expressed as contextual bindings
  (`when()->needs('$currency')->give(...)`) rather than string locator keys.
- Additive and opt-in: existing `getProvidedDependency()` and hand-wired
  `create*()` methods keep working unchanged.

## Quick Reference

| Type | Behavior | Use Case |
|------|----------|----------|
| Regular (binding) | Singleton | Stateless services, repositories |
| Conditional (`addBindingIf`) | Binds only if unbound | Plugin defaults that apps can override |
| Factory | New instance each call | Stateful services, request-scoped |
| Protected | Returns closure as-is | Lazy initialization, callable configs |
| Alias | Points to another service | Backward compatibility, short names |
| Contextual | Different impl per class | Per-controller loggers, context-specific deps |
| `#[Inject]` | Constructor-param opt-in | Explicit concrete override, tool visibility |
| `#[Singleton]` | One cached instance per container | Shared stateful services, no registration needed |
| `#[Factory]` | New instance each resolution | Explicit fresh-instance intent on the class |

## Example

```php
return static function (GacelaConfig $config): void {
    // Singleton
    $config->addBinding(Database::class, MySqlDatabase::class);

    // Factory (new instance each time)
    $config->addFactory('query.builder', static fn($c) =>
        new QueryBuilder($c->get(Database::class))
    );

    // Protected (store callable)
    $config->addProtected('db.factory', static fn() => new Database());

    // Alias
    $config->addAlias('db', Database::class);
};
```

## Underlying container features gacela does not expose

Almost everything `gacela-project/container` offers now has a gacela entry point.
Three things that used to be listed here no longer belong to it: **service
tagging** is `GacelaConfig::tag()` (see
[getting a dependency](getting-a-dependency.md#collect-several-implementations)),
**post-construction hooks** are `GacelaConfig::afterResolving()` (above), and
**`make()` with runtime parameters** is documented above as the supported way to
override a constructor argument.

What is left out, and why:

- **`createScope()`**, the container's per-request child container. Gacela has no
  request lifecycle to hang it on yet — who creates the scope and who drops it is
  the open design question, not the forwarding. Tracked for a later release.
- **`load()` / `loadFile()`**, registration from a plain array or JSON file.
  `gacela.php` plus `GacelaConfig` already own registration end to end; a second
  vocabulary for the same job would be the "two ways of doing the same thing" this
  section exists to prevent.
- **Compiled constructor plans on disk** (`writeCompiledCache()` and the generated
  factories that go with it): re-measured for 2.0 and still not worth it, now with
  a sharper reason than "the saving is small". The saving is real but *smaller than
  the file*: materialising a 300-class plans file costs ~1.4ms per process, while
  resolving all 300 of those classes saves ~0.2ms. Compiling a class costs about six
  times the reflection it avoids, so no subset of classes makes it pay, and a build
  stamp does not rescue it — the cost is hydrating the array, not the per-class
  `stat` it replaces. What removes the repeated reflection is the shared plan cache,
  which is on by default and costs nothing. Nothing is hidden, though: the decorator
  forwards `writeCompiledCache()`, `writeCompiledFactories()`, `useCompiledFactories()`
  and `compileReport()`, each taking an optional build stamp, so an application that
  has measured its own case can wire the files up itself.
