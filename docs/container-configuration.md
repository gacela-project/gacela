# Container Configuration

Gacela provides a powerful dependency injection container that can be configured in your `gacela.php` file.

## Factory Services

Factory services create a new instance every time they are resolved from the container, unlike regular services which are singletons.

### Usage

```php
use Gacela\Framework\Bootstrap\GacelaConfig;

return static function (GacelaConfig $config): void {
    // Register a factory service
    $config->addFactory(LoggerInterface::class, static fn() => new FileLogger());
};
```

### Example: Session-based Services

Factory services are useful for creating new instances with different state:

```php
$config->addFactory('session.handler', static function () {
    return new SessionHandler(uniqid('session_'));
});
```

Every time you resolve `'session.handler'` from the container, you get a new `SessionHandler` instance with a unique ID.

### Example: Request-scoped Services

```php
$config->addFactory(RequestContext::class, static function () use ($request) {
    return new RequestContext($request->getMethod(), $request->getUri());
});
```

## Regular Services vs Factory Services

| Feature | Regular Service | Factory Service |
|---------|----------------|-----------------|
| Instance creation | Once (singleton) | Every call |
| Use case | Stateless services, repositories | Stateful services, builders |
| Memory | Single instance in memory | New instance each time |

## Protected Services

Protected services store closures as-is without invoking them. This is useful when you need to store callable configurations or factory functions that should not be automatically executed by the container.

### Usage

```php
use Gacela\Framework\Bootstrap\GacelaConfig;

return static function (GacelaConfig $config): void {
    // Register a protected service
    $config->addProtected('callable.config', static fn() => createSomeObject());
};
```

When you resolve a protected service from the container, you get the closure itself rather than its execution result.

### Example: Storing Callable Configuration

```php
$config->addProtected('database.factory', static function () {
    return new DatabaseConnection(
        host: getenv('DB_HOST'),
        username: getenv('DB_USER')
    );
});

// Later in your code:
$factory = $container->get('database.factory');
$connection = $factory(); // Execute the closure when you need it
```

### Example: Lazy Initialization

Protected services are useful for lazy initialization where you want to control when the service is created:

```php
$config->addProtected('expensive.service', static function () {
    return new ExpensiveService(); // Only created when closure is invoked
});
```

### Protection from Extensions

Protected services cannot be extended via `extendService()`. The closure is stored as-is and returned unchanged.

## Service Type Comparison

| Feature | Regular Service | Factory Service | Protected Service |
|---------|----------------|-----------------|-------------------|
| Instance creation | Once (singleton) | Every call | Returns closure |
| Invoked by container | Yes | Yes (every time) | No |
| Can be extended | Yes | Yes | No |
| Use case | Stateless services | Stateful services | Callable configs |

## Best Practices

1. **Use factories for stateful objects**: If your service maintains state that changes between calls
2. **Use singletons for stateless services**: Database connections, repositories, helpers
3. **Use protected for callables**: When you need to store factory functions or configurations
4. **Combine with bindings**: You can use `addBinding()` for interfaces and `addFactory()` for implementations

### Example: Combined Usage

```php
return static function (GacelaConfig $config): void {
    // Singleton service (via binding)
    $config->addBinding(DatabaseInterface::class, MySqlDatabase::class);

    // Factory service (new instance each time)
    $config->addFactory(QueryBuilder::class, static fn(Container $c) =>
        new QueryBuilder($c->get(DatabaseInterface::class))
    );

    // Protected service (store callable)
    $config->addProtected('query.builder.factory', static fn(Container $c) =>
        new QueryBuilder($c->get(DatabaseInterface::class))
    );
};
```
