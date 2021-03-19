# Facade

The Facade is the entry point of your module. See an example:

```php
interface ExampleModuleFacadeInterface 
{
    public function runFoo(): void;
}

/**
 * @method ExampleModuleFactory getFactory()
 */
final class ExampleModuleFacade extends AbstractFacade implements ExampleModuleFacadeInterface
{
    public function runFoo(): void
    {
        $this->getFactory()
            ->createFooService()
            ->run();
    }
}
```

A Facade is a ready to use thing:

```php 
$facade = new ExampleModuleFacade();
$facade->runFoo();
```

The Facade uses the Factory to create its domain instances and execute the desired behaviour from them. Nothing less,
nothing more. 
