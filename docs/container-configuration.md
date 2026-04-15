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

## Quick Reference

| Type | Behavior | Use Case |
|------|----------|----------|
| Regular (binding) | Singleton | Stateless services, repositories |
| Factory | New instance each call | Stateful services, request-scoped |
| Protected | Returns closure as-is | Lazy initialization, callable configs |
| Alias | Points to another service | Backward compatibility, short names |
| Contextual | Different impl per class | Per-controller loggers, context-specific deps |
| `#[Inject]` | Constructor-param opt-in | Explicit concrete override, tool visibility |

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
