# Facade

The Facade is the entry point of your module. See an example:

```php
interface ModuleAFacadeInterface 
{
    public function executeFooService(): void;
}

/**
 * @method ModuleAFactory getFactory()
 */
final class ModuleAFacade extends AbstractFacade implements ModuleAFacadeInterface
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
$facade = new ModuleAFacade();
$facade->runFoo();
```

The Facade uses the Factory to create its domain instances and execute the desired behaviour from them. Nothing less,
nothing more. 
