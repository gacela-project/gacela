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

## Best Practices

1. **Use factories for stateful objects**: If your service maintains state that changes between calls
2. **Use singletons for stateless services**: Database connections, repositories, helpers
3. **Combine with bindings**: You can use `addBinding()` for interfaces and `addFactory()` for implementations

### Example: Combined Usage

```php
return static function (GacelaConfig $config): void {
    // Singleton service (via binding)
    $config->addBinding(DatabaseInterface::class, MySqlDatabase::class);

    // Factory service (new instance each time)
    $config->addFactory(QueryBuilder::class, static fn(Container $c) =>
        new QueryBuilder($c->get(DatabaseInterface::class))
    );
};
```
