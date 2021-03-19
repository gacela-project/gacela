# Dependency Provider

This is the place where you can define the dependencies that a particular module has with other modules.

In this example you can see that in the `ModuleOne` we have a service (`FooService`) which needs the
`ModuleTwoFacade` as a dependency, for example. For this we can define the dependency inside the
`ExampleModuleOneDependencyProvider`.

```php
final class ModuleOneFactory extends AbstractFactory
{
    public function createFooService(): FooServiceInterface
    {
        return new FooService(
            $this->getModuleTwoFacade()
        );
    }
    
    private function getModuleTwoFacade(): ModuleTwoFacade
    {
        return $this->getProvidedDependency(ModuleOneDependencyProvider::FACADE_MODULE_TWO);
    }
}

final class ModuleOneDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_MODULE_TWO = 'FACADE_MODULE_TWO';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeModuleTwo($container);
    }

    private function addFacadeModuleTwo(Container $container): void
    {
        $container->set(self::FACADE_MODULE_TWO, fn () => new ModuleTwoFacade());
    }
}
```
