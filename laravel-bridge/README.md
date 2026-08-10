# gacela/laravel-bridge

Gacela inside a Laravel application: the service provider does the wiring every
Laravel/Gacela project would otherwise write by hand.

## Install

```
composer require gacela-project/laravel-bridge
```

```php
// bootstrap/providers.php
return [
    Gacela\LaravelBridge\GacelaServiceProvider::class,
];
```

That alone gives you four things:

1. **Gacela bootstrapped when the application boots** — with `base_path()` as
   the application root, honouring `gacela.php`. Every boot bootstraps again,
   so an application rebooted inside one process (package tests do it
   constantly) runs on its own configuration rather than the previous boot's.
   Note that Octane boots each worker once and reuses it: a request-scoped
   Laravel service listed in `external_services` stays whatever the worker's
   first boot captured.
2. **Laravel services reachable from Gacela** — the ones you list, and only
   those.
3. **Gacela's console commands in `artisan`**, under a `gacela:` prefix.
4. **`artisan optimize` warms Gacela's caches too**, so a deploy has one
   optimize step instead of two. `optimize:clear` clears them again.

Plus `#[Inject]` on Laravel-resolved services, described at the bottom.

## Configuration

```bash
php artisan vendor:publish --tag=gacela-config
```

```php
// config/gacela.php
return [
    'enabled' => true,
    'app_root_dir' => null,          // where gacela.php lives; null means base_path()
    'cache_dir' => null,             // null leaves Gacela's own default in place
    'file_cache' => null,            // null leaves Gacela's own default in place
    'project_namespaces' => ['App'],
    'external_services' => [
        'logger' => 'log',
        Psr\Log\LoggerInterface::class => 'log',
    ],
    'register_commands' => true,
    'command_prefix' => 'gacela:',
];
```

Every key is validated when the provider boots — a mistyped one fails there,
naming the key, instead of quietly configuring nothing. Laravel has no compile
step, so boot is the earliest the check can run.

### External services

`external_services` maps a key to a Laravel binding id. What the key *is*
decides how far the service travels:

```php
'external_services' => [
    Psr\Log\LoggerInterface::class => 'log',   // a type: also bound
    'report_mailer' => 'mailer',               // a plain key: external service only
],
```

A key that **names a class or interface** additionally becomes a Gacela
binding, so it resolves on its own — through `Gacela::get()`, through
autowiring, through `#[Inject]`.

A key that names **no type** stays an external service, which is what your own
`gacela.php` reads when it declares bindings — because a binding maps a *type*
to an implementation, and `report_mailer` is not one:

```php
// gacela.php
$config->addBinding(MailerInterface::class, $config->getExternalService('report_mailer'));
```

Either way the service is fetched from Laravel's container when Gacela asks for
it, so listing one does not construct it — booting the application stays as
cheap as it was.

### Commands

The prefix is not decoration: artisan owns the whole `make:*` namespace, so an
unprefixed `make:module` would collide with it.

```bash
php artisan gacela:make:module App/Blog
php artisan gacela:doctor
php artisan list gacela
```

Set `'register_commands' => false` to leave artisan alone and keep using
`vendor/bin/gacela`.

## `#[Inject]` on Laravel-resolved services

Laravel autowires constructor parameters through its own container, and
Gacela's `#[Inject]` attribute is recognised by Gacela's container only. On a
class managed by Laravel — a controller, a job, a command — writing
`#[Inject]` therefore had no effect: Laravel's autowire claimed the parameter
first.

The bridge closes that gap twice over.

**On a constructor parameter**, use the bridge's attribute with an explicit
class. It implements Laravel's `ContextualAttribute` contract, so Laravel
itself resolves the parameter through Gacela — and because it extends the
Gacela attribute, Gacela honours it too on the classes *it* builds. One
attribute, both containers:

```php
use Gacela\LaravelBridge\Attribute\Inject;

public function __construct(
    #[Inject(ProductFacade::class)] private ProductFacade $facade,
) {
}
```

The class is required there: Laravel hands a contextual attribute no parameter
to read a type from. Leaving it off fails with directions, not with a silently
autowired substitute.

**On a property or a setter**, the bare form works — the type is on the member.
The provider listens to `afterResolving` and injects into every instance
Laravel builds, honouring the attribute under either namespace:

```php
use Gacela\Container\Attribute\Inject;

final class SyncStock implements ShouldQueue
{
    #[Inject]
    private ProductFacade $facade;
}
```

A `readonly` property is refused by name — it cannot be written after
construction — and so is a static or non-public setter.

## Status

Experimental. API may change until it graduates out of `gacela/gacela`'s
`laravel-bridge/` subfolder.
