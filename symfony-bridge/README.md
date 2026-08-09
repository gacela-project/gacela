# gacela/symfony-bridge

Gacela inside a Symfony application: the bundle does the wiring every
Symfony/Gacela project would otherwise write by hand.

## Install

```
composer require gacela-project/symfony-bridge
```

```php
// config/bundles.php
return [
    Gacela\SymfonyBridge\GacelaBundle::class => ['all' => true],
];
```

That alone gives you four things:

1. **Gacela bootstrapped from the kernel** — with the project dir as the
   application root, honouring `gacela.php`. Every boot bootstraps again, so a
   kernel rebooted inside one process (functional tests do it constantly) runs
   on its own configuration rather than the previous boot's.
2. **Symfony services reachable from Gacela** — the ones you list, and only
   those.
3. **Gacela's console commands in `bin/console`**, under a `gacela:` prefix.
4. **`cache:warmup` warms Gacela's caches too**, so a deploy has one warmup step
   instead of two.

Plus the `#[Inject]` compiler pass, described at the bottom.

## Configuration

```yaml
# config/packages/gacela.yaml
gacela:
    app_root_dir: '%kernel.project_dir%'   # where gacela.php lives
    cache_dir: '%kernel.cache_dir%/gacela'
    file_cache: true
    project_namespaces: ['App']
    external_services:
        logger: 'monolog.logger'
        entity_manager: 'doctrine.orm.entity_manager'
    register_commands: true
    command_prefix: 'gacela:'
```

Every key is validated at compile time — a mistyped one fails the build instead
of quietly configuring nothing. `cache_dir` and `file_cache` left unset leave
Gacela's own defaults in place.

### External services

`external_services` maps a key to a Symfony service id. What the key *is*
decides how far the service travels:

```yaml
gacela:
    external_services:
        Psr\Log\LoggerInterface: 'monolog.logger'   # a type: also bound
        report_mailer: 'app.mailer'                 # a plain key: external service only
```

A key that **names a class or interface** additionally becomes a Gacela binding,
so it resolves on its own — through `Gacela::get()`, through autowiring, through
`#[Inject]`:

```php
public function __construct(
    #[Inject] private LoggerInterface $logger,
) {
}
```

A key that names **no type** stays an external service, which is what your own
`gacela.php` reads when it declares bindings — because a binding maps a *type*
to an implementation, and `report_mailer` is not one:

```php
// gacela.php
$config->addBinding(MailerInterface::class, $config->getExternalService('report_mailer'));
```

Either way the service is fetched through a service locator when Gacela asks for
it, so listing one does not construct it — booting the kernel stays as cheap as
it was.

### Commands

The prefix is not decoration: Symfony's MakerBundle owns the whole `make:*`
namespace, so an unprefixed `make:module` would collide with it.

```bash
bin/console gacela:make:module App/Blog
bin/console gacela:doctor
bin/console list gacela
```

Set `register_commands: false` to leave `bin/console` alone and keep using
`vendor/bin/gacela`.

## The `#[Inject]` compiler pass

Symfony autowires constructor parameters through its own container, and Gacela's
`#[Inject]` attribute is recognised by Gacela's container only. On a class
managed by Symfony — most often a `Command` — writing `#[Inject]` therefore had
no effect: Symfony's autowire claimed the parameter first.

`GacelaInjectCompilerPass` walks every service definition at compile time, looks
at each constructor parameter for `#[Inject]`, and rewrites the argument so
Symfony resolves that slot through Gacela's container instead. If both
containers claim the same parameter, the build fails naming the service and
parameter.

The bundle registers the pass for you. To use it without the bundle:

```php
use Gacela\SymfonyBridge\GacelaInjectCompilerPass;

$container->addCompilerPass(new GacelaInjectCompilerPass());
$container->set('gacela.container', Gacela::container());
```

The Gacela container must be registered as a Symfony service named
`gacela.container` so the rewritten arguments can resolve through it at runtime.

## Status

Experimental. API may change until it graduates out of `gacela/gacela`'s
`symfony-bridge/` subfolder.
