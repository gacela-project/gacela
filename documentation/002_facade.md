[Back to the index](../documentation)

# Facade

The Facade is the entry point of your module. See an example:

```php
# src/Calculator/CalculatorFacadeInterface.php
interface CalculatorFacadeInterface 
{
    public function sum(): void;
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
        $this->getFactory()
            ->createAdder()
            ->add(...$numbers);
    }
}
```

A Facade is a "ready to use" thing:

```php 
$facade = new CalculatorFacade();
$result = $facade->sum(2, 3);  
```

The Facade uses the Factory to create the module's domain instances and executes the desired behaviour from them. 
Nothing less, nothing more. 
