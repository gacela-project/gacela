[Back to the index](../documentation)

# Config

You can simply use it in order to construct your business domain classes by injecting the data 
from the Config using the Factory when you do the creation of your domain classes.

Key-points here:

- The Config will get the data from the `config.php`.
- The data is easily accessible by using the `$this->get('key')`.
- The Factory is the only one who can access the Config.

> This is tightly coupled with the infrastructure layer, because there is IO involved. 
> It's not bad itself, you just need to be aware of this, though. Bad will be if you use 
> this directly in your domain services, because you would couple them with infrastructure code, 
> and you would not be able to unit test them.

### An example

```php
# src/config.php
use YourApp\Calculator\CalculatorConfig;

$config[CalculatorConfig::MAX_ADDITIONS] = 20;
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
