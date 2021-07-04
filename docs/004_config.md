[Back to the index](../docs)

# Config

Use a Config Class to construct your business domain classes by injecting the data from the Config using the Factory
when you do the creation of your domain classes.

In order to achieve that, you need to create a `gacela.json` file in your application root with the following values:

### gacela.json examples

Using PHP config files:

```json
{
  "config": {
    "type": "php",
    "path": "config/*.php",
    "path_local": "config/local.php"
  }
}
```

Using `.env` config files:

```json
{
  "config": {
    "type": "env",
    "path": ".env*",
    "path_local": ".env.local.dist"
  }
}
```

- `type`: enum with possible values `php` or `env`
- `path`: this is the path of the folder which contains your application configuration.
   You can use `?` or `*` in order to match 1 or multiple characters. Check [`glob()`](https://www.php.net/manual/en/function.glob.php) function for more info.
- `path_local`: this is the last file loaded, which means, it will override the previous configuration,
  so you can easily add it to your `.gitignore` and set your local config values in case you want to have something different for some cases

---

> This is tightly coupled with the infrastructure layer, because there is I/O involved.
> It's not bad itself, you just need to be aware of potential risks, though. Don't
> access data from your `config` files (files under the gacela.json `path` directory) directly in your domain services.
> In this way, you would couple your logic with infrastructure code, and not be able to unit test it.

### An example

```php
# config/default.php
use src\Calculator\Config;

return [
    Config::MAX_ADDITIONS => 20,
];
```

```php
# src/Calculator/Config.php
final class Config extends AbstractConfig
{
    public const MAX_ADDITIONS = 'MAX_ADDITIONS';

    public function getMaxAdditions(): int
    {
        return $this->get(self::MAX_ADDITIONS, $default = 0);
    }
}
```

```php
# src/Calculator/Factory.php
/**
 * @method Config getConfig()
 */
final class Factory extends AbstractFactory
{
    public function createAdder(): AdderInterface
    {
        return new Adder(
            $this->getConfig()->getMaxAdditions()
        );
    }
}
```

[<< Factory](../docs/003_factory.md) | [Dependency Provider >>](../docs/005_dependency_provider.md)
