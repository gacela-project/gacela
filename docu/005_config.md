# Config

This is tightly coupled with the infrastructure layer, because there is IO involved!
It's not bad itself, you just need to be aware of this, though. Bad will be if you use this directly in your domain
services, then you would couple them with infrastructure code, and you would not be able to unit test them.

The Config is used only via the Factory. How to properly use by injecting the data from the config using the Factory
when you do the creation of your domain classes.

See an example:

```php
final class ModuleAConfig extends AbstractConfig
{
public const NUMBER_OF_BARS = 'NUMBER_OF_BARS';

    public function getNumberOfBars(): int
    {
        return $this->get(self::NUMBER_OF_BARS, $default = 0);
    }
    // ...
}

/**
 * @method ModuleAConfig getConfig()
 */
final class ModuleAFactory extends AbstractFactory
{
    public function createFooService(): FooServiceInterface
    {
        return new FooService(
            $this->getConfig()->getNumberOfBars()
        );
    }
    // ...
}
```

The Config is reading the data from the `app-src/config/config_default.php`. The data is easily accessible by using
the `$this->get('key')` from Config.

An example of that `config_default.php` would be:

```
php use YourApp\ModuleA\ModuleAConfig;

$config[ModuleAConfig::NUMBER_OF_BARS] = 123;
```
