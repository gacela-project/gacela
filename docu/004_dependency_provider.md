# Dependency Provider

This is the place where you can define the dependencies that a particular module has with other modules.

In this example you can see that in the ModuleA we have a service (FooService) which needs the ModuleBFacade as a
dependency, for example. For this we can define the dependency inside the
`ModuleADependencyProvider` using the locator (which uses the magic __call function to know what module do you really
want) and then the `facade()`

> $container->getLocator()->moduleb()->facade();

```php
final class ModuleAFactory extends AbstractFactory
{
    public function createFooService(): FooServiceInterface
    {
        return new FooService(
            $this->getModuleBFacade()
        );
    }
    
    private function getModuleBFacade(): ModuleBFacade
    {
        return $this->getProvidedDependency(ModuleADependencyProvider::FACADE_MODULE_B);
    }
}

final class ModuleADependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_MODULE_B = 'FACADE_MODULE_B';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeModuleB($container);
    }

    private function addFacadeModuleB(Container $container): void
    {
        $container->set(self::FACADE_MODULE_B, function (Container $container): ModuleBFacadeInterface {
            /** @var ModuleBFacadeInterface $facade */
            $facade = $container->getLocator()->moduleb()->facade();

            return $facade;
        });
    }
}
```

In this example you can see how you can communicate the module-b with the module-a.
