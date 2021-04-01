[Back to the index](../docs)

# Factory

The Factory is the place where you create your domain services and objects. 
The Facade is the class that can access the Factory.

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
final class CalculatorFacade extends AbstractFacade implements CalculatorFacadeInterface
{
    public function sum(int ...$numbers): int
    {
        return $this->getFactory()
            ->createAdder()
            ->add(...$numbers);
    }
}
```
