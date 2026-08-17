# Getting Started

This short guide shows how to add **Gacela** to a fresh PHP project and build your first module.

## 1. Install

```bash
composer require gacela-project/gacela
```

## 2. Create the `gacela.php` bootstrap file

`vendor/bin/gacela init` scaffolds it for you. To write it by hand:

```php
<?php
declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;

return static function (GacelaConfig $config): void {
    $config->addAppConfig('config/*.php');
};
```

### Config precedence

When the same key appears in several places, later sources win:

1. default config files matching the pattern (e.g. `config/app.php`), minus the environment layers among them — see below
2. environment-suffixed files (`config/app-{APP_ENV}.php`, using the `APP_ENV` env var)
3. one file per declared [config dimension](#config-dimensions), each refining the one before it
4. the local file (second argument of `addAppConfig(...)`, conventionally `config/local.php`; not env-suffixed, meant for per-machine overrides)
5. values set in code via `GacelaConfig::addAppConfigKeyValue(s)`

#### The base layer excludes the environment files

`config/*.php` is a glob, and it matches `config/app-prod.php` as happily as `config/app.php`. Left at that, step 1 would read every environment's file before step 2 got to choose one, and a key set *only* in `config/app-prod.php` would reach a developer's machine — the base layer having nothing to overwrite it with.

So a match is excluded from the base layer when stripping one or more trailing `-<segment>` parts from its basename yields **another file the same pattern matched**:

```
config/app.php             → base layer
config/app-prod.php        → app          → a file: layer of app.php, read when APP_ENV=prod
config/app-prod-eu.php     → app-prod, app → a file: layer of app.php, read when APP_ENV=prod APP_REGION=eu
config/local.php           → nothing to strip: base layer
```

Anchored on another matched file, so the exclusion can never empty a base layer: a directory holding only `config/app-prod.php` still reads it, because there is no `app.php` for it to be a layer of.

The rule is about names, and a name carries no intent — a `config/app-extra.php` you wrote for some other reason is excluded too, and read only when the chain resolves to `extra`. `vendor/bin/gacela doctor` names every file it excludes this way, with the base file it is taken to refine and the values that put it in play, so that case is reported rather than silent. Rename such a file, or give it its own `addAppConfig()` path.

Every boot re-reads and re-merges these files. That is what you want while developing, and what you do not want in production: `enableFileCache()` collapses the merged result into a single PHP file per app root, `APP_ENV` and dimension tuple, so warm boots skip the scan entirely, and `cache:clear` drops it. It is **off by default** — see [production performance](production-performance.md).

### Config dimensions

`APP_ENV` answers *which environment*. A project that also varies configuration by region, tenant or brand declares further variables that answer the same way:

```php
// gacela.php
$config->addConfigDimension('APP_REGION');
$config->addConfigDimension('APP_TENANT');
```

With `APP_ENV=prod APP_REGION=eu`, the chain is `config/app.php`, then `config/app-prod.php`, then `config/app-prod-eu.php`. Each link refines the one before it, so the most specific file wins and the others still contribute the keys it does not mention.

Three rules worth knowing:

- **Declaration order is chain order.** `APP_REGION` then `APP_TENANT` means `app-prod-eu-acme.php`, never `app-prod-acme-eu.php`.
- **An unset variable ends the chain.** With no `APP_REGION`, a tenant is never consulted — `app-prod--acme.php` would be a file with a hole in it and no meaning.
- **Values are restricted** to letters, digits, `_`, `.` and `-`. A dimension reaches both a glob pattern and a cache filename, so anything else is refused at bootstrap rather than resolving somewhere unintended. `APP_ENV` is the first link of the same chain and is held to the same alphabet.

Because each link refines the one before it, a file in the chain only needs the keys it changes — and it is the *only* place those keys have to exist. The base file does not have to name every key an environment sets: [the base layer excludes these files](#the-base-layer-excludes-the-environment-files), so a production-only key stays out of every other environment.

The merged-config cache is named by the whole tuple, so two regions sharing one cache directory never read each other's file. `cache:warm` warms the tuple it runs as, so a deploy that serves several runs it once per tuple:

```bash
APP_REGION=eu vendor/bin/gacela cache:warm
APP_REGION=us vendor/bin/gacela cache:warm
```

`gacela.php` itself does **not** grow dimension variants: it is read in order to *learn* which dimensions exist, so a `gacela-prod-eu.php` could never declare one.

## 3. Add your first module

```
src/
└── Hello
    ├── Facade.php
    ├── Factory.php
    └── Greeter.php
```

`src/Hello/Facade.php`
```php
<?php
declare(strict_types=1);

namespace App\Hello;

use Gacela\Framework\AbstractFacade;

final class Facade extends AbstractFacade
{
    public function sayHello(): string
    {
        return $this->getFactory()->createGreeter()->greet();
    }
}
```

`src/Hello/Factory.php`
```php
<?php
declare(strict_types=1);

namespace App\Hello;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createGreeter(): Greeter
    {
        return $this->singleton(Greeter::class, fn () => new Greeter());
    }
}
```

> Using `singleton()` the factory keeps instances in memory.

### Config and Provider are optional pillars

A Gacela module has four pillars — **Facade**, **Factory**, **Config**, and **Provider** — but only the **Facade** and **Factory** are required. The runtime tolerates the other two being absent: Config falls back to an anonymous default, and a module whose Factory never calls `getProvidedDependency()` never needs a Provider. So the two-file module above already resolves and runs.

Add the optional pillars only when you actually need them:

- **Config** — when the module reads configuration values.
- **Provider** — when the module wires external dependencies (services from other modules or third-party libraries) into its container.

Scaffold just the two-file floor with the CLI:

```bash
vendor/bin/gacela make:module App/Hello --minimal --short-name
```

`--short-name` is what writes `Facade.php` rather than `HelloFacade.php`, matching the tree above; drop it for the prefixed names the rest of the docs use. Use `make:module App/Hello` (or `--template=basic`) for the full four-pillar shape, or `--template=service` for a module wired to a `Domain` service.

`src/Hello/Greeter.php`
```php
<?php
declare(strict_types=1);

namespace App\Hello;

final class Greeter
{
    public function greet(): string
    {
        return 'Hello Gacela!';
    }
}
```

## 4. Bootstrap the application

```php
use Gacela\Framework\Gacela;

require __DIR__ . '/vendor/autoload.php';

Gacela::bootstrap(__DIR__);
```

Now you can use your facade:

```php
$hello = (new \App\Hello\Facade())->sayHello();
```

## Next steps

See the [official documentation](https://gacela-project.com/) and the [example repository](https://github.com/gacela-project/gacela-example) for more advanced usage.
