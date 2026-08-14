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

1. default config files matching the pattern (e.g. `config/app.php`)
2. environment-suffixed files (`config/app-{APP_ENV}.php`, using the `APP_ENV` env var)
3. one file per declared [config dimension](#config-dimensions), each refining the one before it
4. the local file (second argument of `addAppConfig(...)`, conventionally `config/local.php`; not env-suffixed, meant for per-machine overrides)
5. values set in code via `GacelaConfig::addAppConfigKeyValue(s)`

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
