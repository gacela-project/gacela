# Shipping a Gacela package

A Composer package can contribute to a Gacela application by being installed.
It declares its configuration in its own `composer.json`:

```json
{
    "name": "acme/invoice-audit",
    "extra": {
        "gacela": {
            "config": "config/gacela.php"
        }
    }
}
```

and that file returns the same thing a project's own `gacela.php` returns — a
`callable(GacelaConfig): void`:

```php
<?php // config/gacela.php, in the package

declare(strict_types=1);

use Acme\InvoiceAudit\AuditChannel;
use Gacela\Framework\Bootstrap\GacelaConfig;

return static function (GacelaConfig $config): void {
    $config->addPluginStack(NotificationChannelInterface::class, [AuditChannel::class]);
};
```

There is no second config format and no registration API to learn. The
consuming application writes nothing:

```bash
composer require acme/invoice-audit
```

## Read the security note first

**A discovered config is arbitrary PHP, executed inside `Gacela::bootstrap()`,
in your application's process, with everything your application can reach.**

That is the same bargain a Laravel service provider registered through
`extra.laravel.providers` already asks for, and the same one a Composer install
script asks for — but it is worth saying plainly rather than leaving it to be
inferred, because `composer require` is the whole of the installation step.

`GacelaConfig::dontDiscover()` is the control:

```php
// gacela.php, in the application
return static function (GacelaConfig $config): void {
    // Named packages: the file is never opened.
    $config->dontDiscover(['acme/legacy-invoicing']);

    // Everything, installed now or later: no package's file is opened at all.
    $config->dontDiscover(['*']);
};
```

Naming a package means its config is never *read*, not that its effects are
undone afterwards. `dontDiscover(['*'])` is checked before the manifest is
touched, so a project that wants nothing but its own `gacela.php` deciding what
runs at boot pays nothing for the feature and reads nothing from `vendor/`.

Both forms are read from the bootstrap closure and from `gacela.php`, and they
accumulate across the two — a closure and a `gacela.php` that each refuse a
package are refusing both. **They cannot be read from `gacela-{APP_ENV}.php`**:
an environment file is merged *after* the packages, so an opt-out written there
would arrive once the code it refuses had already run. `doctor` reports one
written there rather than letting it look effective.

## What a package can contribute

Everything on `GacelaConfig`, which is the point of not inventing a second
format. In practice:

| | |
|---|---|
| bindings | `addBinding()`, `addFactory()`, `addLazy()`, `addProtected()`, `addAlias()` |
| extension points | `addPluginStack()`, `addHandlerRegistry()`, `tag()`, `extendService()`, `extendProviderService()` |
| behaviour at boot | `addPlugin()`, `registerSpecificListener()`, `registerGenericListener()` |
| declarations | `declareConfigSchema()`, `declareDtoSchema()`, `addResolvableType()`, `addSuffixType*()` |
| configuration values | `addAppConfigKeyValue()` |
| health | `addHealthCheck()` |

Two things a package should not reach for:

- **`addAppConfig()`** — a config path resolves against the *application* root,
  which the package knows nothing about. Ship `addAppConfigKeyValue()` defaults
  instead, and let the application's own config layers override them.
- **`setProjectNamespaces()` / `setAppModulePaths()`** — these describe the
  application, and the merger *replaces* the module paths rather than appending,
  so a package setting them would take the application's own list away.

## Merge order

```
bootstrap closure  →  package 1  →  package 2  →  …  →  gacela.php  →  gacela-{APP_ENV}.php
```

Packages are merged in **Composer's installed order**, and the project's own
configuration is merged after all of them — so **the project always has the last
word**. Overriding a default a package set is one line in `gacela.php`:

```php
$config->addBinding(AuditSinkInterface::class, OurOwnSink::class);
```

Between two packages that declare the same thing, the later-installed one wins.
**Do not rely on that.** Installed order is Composer's, decided by the dependency
graph and by when things were added to `composer.json`; no application controls
it, and it can change under a `composer update` that touches neither package. A
package that needs to win should give the consuming project something to call
instead.

For anything *appended* rather than replaced — a plugin stack, a tag, a listener
list — being merged first means being **first in the list**. A package's channel
runs before the application's own:

```
$ vendor/bin/gacela debug:container --stats
…
Discovered packages:
  1. acme/invoice-audit
     /app/vendor/acme/invoice-audit/config/gacela.php
     plugin stacks: NotificationChannelInterface => AuditChannel
```

## A broken declaration does not stop the boot

`composer require` must never be able to stop an application from booting, so a
declaration that cannot do what it says is skipped rather than fatal:

- the file named by `extra.gacela.config` is not there;
- the file is there and does not return a `callable(GacelaConfig)`.

Both are silent at boot and reported by `doctor`:

```
$ vendor/bin/gacela doctor
⚠ discovered packages
    acme/invoice-audit declares /app/vendor/acme/invoice-audit/config/gacela.php, which file not found
    → fix the `extra.gacela.config` path in the package, or drop the key; …
```

The same check reports an opt-out that refuses nothing — one written in an
environment file, and one naming a package that declares no config — because a
`dontDiscover()` entry that looks like it is working is worse than none.

An application root with no `vendor/composer/installed.json` discovers nothing,
silently. That is a checkout with no install, or a fixture directory used as an
app root, and it is not a fault.

## Seeing what a boot picked up

Three reports, and one event.

`debug:container --stats` names each discovered package, the file that ran, and
what that file declared — the one source in an application a reader cannot find
by searching it, because nothing in the project names the package. It names the
refused ones too, so "opted out" is distinguishable from "not installed".
`--json` carries the same under a `packages` key, and the `dontDiscover()` list
that decided it under `optedOut` — including an entry that refused nothing,
which appears in neither of the other two lists.

`debug:events` lists a listener a package registered on the same terms as one the
application registered: the listener registry is read off the configuration, so
there is no blind spot for code nothing in the project names.

`doctor` gives the verdict, from the same capture `debug:container` describes.

And [`PackageConfigMergedEvent`](events.md) fires once per discovered package,
carrying `packageName()`, `configFile()` and `position()` — the 1-based place in
the merge order:

```php
$config->registerSpecificListener(
    PackageConfigMergedEvent::class,
    static fn (PackageConfigMergedEvent $event) => $logger->info($event->toString()),
);
```

It is dispatched *after* the whole configuration is assembled rather than as each
package is merged. The dispatcher is derived from the merged listeners, so firing
during the merge would fire before the project's own listeners exist — and
`gacela.php` is the natural place to write down what a boot picked up.

## What it costs

`vendor/composer/installed.json` is a few hundred kilobytes of JSON in a real
application, and decoding it on every boot to find out that nothing declares
anything is the cost worth avoiding. So the resolved list of config files is
cached in the cache directory, keyed by that file's own size and modification
time: a `composer install` moves the key and the previous answer stops being an
answer. Nothing else has to invalidate it.

The warm path is therefore a `stat` and an `include` of a small PHP array. An
application where **no** package declares the key pays one `stat`, and one whose
root was never `composer install`ed pays one `is_file`.

Two things switch the cache off, both deliberately:

- `setFileCache(false)` — the same trade a project already makes for class
  resolution: every boot re-reads the manifest, and nothing is written to disk.
- `dontDiscover(['*'])` — nothing is read, so there is nothing to cache.

`cache:warm` and `cache:clear` treat the file like every other cache file.

## Checklist for a package author

1. `extra.gacela.config` in `composer.json`, pointing at a file that returns
   `callable(GacelaConfig): void`.
2. `require` on `gacela-project/gacela`, so the package declares what it imports
   (`doctor` checks this too).
3. Publish your extension points as interfaces and declare the stacks yourself:
   `addPluginStack(YourContract::class, [YourDefault::class])`. A consuming
   application appends to the same stack by naming the interface, which is then
   the only thing it has to know about your package.
4. Bind defaults, do not enforce them. The application is merged last; write the
   documentation that says which binding to override.
5. Keep the config file declarative. It runs during `Gacela::bootstrap()`, in
   somebody else's process, before their application exists — read no
   configuration, touch no network, write no files.
6. Say in your README that installing the package runs code at boot, and that
   `dontDiscover(['your/package'])` is how a consumer declines.

## See also

- [Getting started](getting-started.md) — the configuration surface a package shares with an application
- [Container configuration](container-configuration.md) — plugin stacks, handler registries, tags
- [Events](events.md) — `PackageConfigMergedEvent`, and the dispatch model a package's listener joins
- [CLI commands](cli.md) — `debug:container`, `doctor`
- [Reference application](reference-app.md) — two packages installed against it, one kept and one refused
