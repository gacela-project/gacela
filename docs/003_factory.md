[Back to the index](../docs)

# Factory

The Factory is the place where you create your domain services and objects.
The Facade is the class that can access the Factory.

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
            // ...
        );
    }
}
```

```php
# src/Calculator/Facade.php
/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade implements FacadeInterface
{
    public function sum(int ...$numbers): int
    {
        return $this->getFactory()
            ->createAdder()
            ->add(...$numbers);
    }
}
```

[<< Facade](../docs/002_facade.md) | [Config >>](../docs/004_config.md)
