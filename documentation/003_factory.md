# Factory

The Factory is the place where the creation from your domain services and objects happens.
To the Factory can access ONLY the Facade. 

```php
/**
 * @method ExampleModuleConfig getConfig()
 */
final class ExampleModuleFactory extends AbstractFactory
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
