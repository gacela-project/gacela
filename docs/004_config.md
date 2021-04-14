[Back to the index](../docs)

# Config

Use a Config Class to construct your business domain classes by injecting the data from the Config using the Factory
when you do the creation of your domain classes.

Key-points here:

- The `Config` will get the data from all php files under the `config` directory.
- The data is easily accessible by using the `$this->get('key')`.
- The `Factory` is the only class that can access the `Config`.

Extra:

- The `config/local.php` will be loaded the last one. So you can easily add it to your `.gitignore` and set your local
  config values in case you want to have something different for some cases.

> This is tightly coupled with the infrastructure layer, because there is I/O involved.
> It's not bad itself, you just need to be aware of potential risks, though. Don't
> access data from your `config` files (files under the `config` directory) directly in your domain services.
> In this way, you would couple your logic with infrastructure code, and not be able to unit test it.

### An example

```php
# config/default.php
use src\Calculator\CalculatorConfig;

return [
    CalculatorConfig::MAX_ADDITIONS => 20,
];
```

```php
# src/Calculator/CalculatorConfig.php
final class CalculatorConfig extends AbstractConfig
{
    public const MAX_ADDITIONS = 'MAX_ADDITIONS';

    public function getMaxAdditions(): int
    {
        return $this->get(self::MAX_ADDITIONS, $default = 0);
    }
}
```

```php
# src/Calculator/CalculatorFactory.php
/**
 * @method CalculatorConfig getConfig()
 */
final class CalculatorFactory extends AbstractFactory
{
    public function createAdder(): AdderInterface
    {
        return new Adder(
            $this->getConfig()->getMaxAdditions()
        );
    }
}
```
