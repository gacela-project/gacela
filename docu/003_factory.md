# Factory

The Factory is the place where the creation from your domain services and objects happens.
To the Factory can access ONLY the Facade. 

```php
/**
 * @method ModuleAConfig getConfig()
 */
final class ModuleAFactory extends AbstractFactory
{
    public function createFooService(): FooServiceInterface
    {
        return new FooService(
            // ...
        );
    }
    //...
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
