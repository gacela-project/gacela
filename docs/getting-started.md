# Getting Started

This short guide shows how to add **Gacela** to a fresh PHP project and build your first module.

## 1. Install

```bash
composer require gacela-project/gacela
```

## 2. Create the `gacela.php` bootstrap file

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
3. the local file (second argument of `addAppConfig(...)`, conventionally `config/local.php`; not env-suffixed, meant for per-machine overrides)
4. values set in code via `GacelaConfig::addAppConfigKeyValue(s)`

On the first boot the merged result is cached to a single PHP file (per `APP_ENV`
and app root), so warm boots skip the file scan entirely; `cache:clear` drops it.

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

A Gacela module has four pillars — **Facade**, **Factory**, **Config**, and
**Provider** — but only the **Facade** and **Factory** are required. The
runtime tolerates the other two being absent: Config falls back to an anonymous
default, and a module whose Factory never calls `getProvidedDependency()` never
needs a Provider. So the two-file module above already resolves and runs.

Add the optional pillars only when you actually need them:

- **Config** — when the module reads configuration values.
- **Provider** — when the module wires external dependencies (services from
  other modules or third-party libraries) into its container.

Scaffold just the two-file floor with the CLI:

```bash
vendor/bin/gacela make:module App/Hello --minimal
```

Use `make:module App/Hello` (or `--template=basic`) for the full four-pillar
shape, or `--template=service` for a module wired to a `Domain` service.

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
