# Dependency Provider

This is the place where you can define the dependencies that a particular module has with other modules.

In this example you can see that in the ExampleFirstModule we have a service (FooService) which needs the SecondModuleFacade as a
dependency, for example. For this we can define the dependency inside the
`ExampleFirstModuleDependencyProvider` using the locator (which uses the magic __call function to know what module do you really
want) and then the `facade()`

> $container->getLocator()->secondModule()->facade();

```php
final class ExampleFirstModuleFactory extends AbstractFactory
{
    public function createFooService(): FooServiceInterface
    {
        return new FooService(
            $this->getSecondModuleFacade()
        );
    }
    
    private function getSecondModuleFacade(): SecondModuleFacade
    {
        return $this->getProvidedDependency(ExampleFirstModuleDependencyProvider::FACADE_SECOND_MODULE);
    }
}

final class ExampleFirstModuleDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_SECOND_MODULE = 'FACADE_SECOND_MODULE';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeSecondModule($container);
    }

    private function addFacadeSecondModule(Container $container): void
    {
        $container->set(self::FACADE_SECOND_MODULE, function (Container $container): SecondModuleFacadeInterface {
            /** @var SecondModuleFacadeInterface $facade */
            $facade = $container->getLocator()->secondModule()->facade();

            return $facade;
        });
    }
}
```

In this example you can see how you can communicate the second-module with the first-module.
