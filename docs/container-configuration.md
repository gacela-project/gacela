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

### Resolution order

For `#[Inject($override)] Type $p` on a class `Consumer`, the container tries:

1. `$override` set → resolve `$override`.
2. `$config->when(Consumer)->needs(Type)->give(X)` → resolve `X`.
3. `$config->addBinding(Type, X)` → resolve `X`.
4. `Type` is an instantiable class → `new Type(...)` with recursive autowire.
5. `$p` has a default → use it.
6. Otherwise → throw `ServiceNotFoundException`.

Nullable parameters (`?Foo`) with no binding and no default resolve to `null`.
Every other miss is an exception.

### Interactions

- Contextual bindings win over global `addBinding` (step 2 before step 3).
- Protected services (`addProtected`) cannot be injected — they're stored
  as raw closures and the container won't instantiate them.
- `#[Inject]` does not replace `#[ServiceMap]` or `ServiceResolverAwareTrait`
  — those serve a different `__call`-based dispatch use case and remain
  supported.

### Visibility in tooling

`bin/gacela debug:dependencies <Class>` lists every constructor parameter
with its resolution kind. `#[Inject]` parameters show up tagged `inject`,
with the override concrete rendered inline when present:

```
✓ $logger LoggerInterface (inject)
✓ $cache CacheInterface (inject -> App\Cache\RedisCache)
```

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

## Class Attributes: `#[Singleton]` and `#[Factory]`

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

Notes:

- `#[Singleton]` instances are cached per container. Each Gacela module factory
  keeps one container, so repeated resolves through the same module share the
  instance.
- Without any attribute, an unregistered class is autowired fresh on each
  `get()` — `#[Singleton]` is the opt-in for caching, `#[Factory]` documents
  the fresh-instance intent explicitly.
- Constructor params of attribute-annotated classes still go through the normal
  resolution order (bindings, contextual bindings, `#[Inject]`).

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

The `gacela-project/container` package offers a few capabilities that gacela
deliberately keeps internal, to avoid two ways of doing the same thing:

- **Service tagging** (`tag()` / `tagged()`): use [`addHandlerRegistry()`](events.md)
  instead. Handler registries are a superset — lazily resolved, keyed dispatch
  tables, frozen after boot — so a flat tag group would only duplicate a weaker
  version of the same idea.
- **`afterResolving()` hooks**: use the [`ServiceResolvedEvent`](events.md) instead.
  It is the gacela-native, zero-cost-when-unused way to react to resolution.
- **`make()` with runtime parameters**: kept internal. One-off construction with
  ad-hoc overrides encourages service location; resolve through your module's
  Factory instead.
- **Compiled constructor plans**: measured on gacela's instance-cached resolution,
  the reflection saved is sub-microsecond per class, so the cache-file lifecycle is
  not worth its complexity today. May be revisited if bootstrap reflection ever
  shows up as a real hotspot for large applications.
