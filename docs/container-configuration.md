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

## Quick Reference

| Type | Behavior | Use Case |
|------|----------|----------|
| Regular (binding) | Singleton | Stateless services, repositories |
| Factory | New instance each call | Stateful services, request-scoped |
| Protected | Returns closure as-is | Lazy initialization, callable configs |
| Alias | Points to another service | Backward compatibility, short names |
| Contextual | Different impl per class | Per-controller loggers, context-specific deps |

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
