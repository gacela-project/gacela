[Back to the index](../docs)

# Facade

The Facade is the entry point of your module. See an example:

```php
# src/Calculator/FacadeInterface.php
interface FacadeInterface 
{
    public function sum(): void;
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
        $this->getFactory()
            ->createAdder()
            ->add(...$numbers);
    }
}
```

A Facade is a "ready to use" thing:

```php 
$facade = new Facade();
$result = $facade->sum(2, 3);  
```

The Facade uses the Factory to create the module's domain instances and executes the desired behaviour from them.
Nothing less, nothing more. 

[<< Basic Concepts](../docs/001_basic_concepts.md) | [Factory >>](../docs/003_factory.md)
