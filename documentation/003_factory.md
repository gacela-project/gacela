[Back to the index](../documentation)

# Factory

The Factory is the place where you create your domain services and objects. 
The Facade is the only one who can access the Factory.

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
            // ...
        );
    }
}
```

```php
# src/Calculator/CalculatorFacade.php
/**
 * @method CalculatorFactory getFactory()
 */
final class CalculatorFacade extends AbstractFacade implements ModuleAFacadeInterface
{
    public function sum(int ...$numbers): int
    {
        return $this->getFactory()
            ->createAdder()
            ->add(...$numbers);
    }
}
```
