[Back to the index](../docs)

# Dependency Provider

This is the place where you can define the dependencies that a particular module has with other modules.

In this example you can see that in the `Calculator` we have a service (`Adder`) which needs the `AnotherModuleFacade`
as a dependency. In this case, we can define the dependency inside the `DependencyProvider`.

```php
# src/Calculator/Factory.php
final class Factory extends AbstractFactory
{
    public function createAdder(): AdderInterface
    {
        return new Adder(
            $this->getAnotherModuleFacade()
        );
    }
    
    private function getAnotherModuleFacade(): AnotherModuleFacade
    {
        return $this->getProvidedDependency(DependencyProvider::FACADE_ANOTHER_MODULE);
    }
}
```

```php
# src/Calculator/DependencyProvider.php
final class DependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_ANOTHER_MODULE = 'FACADE_ANOTHER_MODULE';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeAnotherModule($container);
    }

    private function addFacadeAnotherModule(Container $container): void
    {
        $container->set(self::FACADE_ANOTHER_MODULE, function (Container $container): AnotherModuleFacade {
            return $container->getLocator()->get(AnotherModuleFacade::class);
        });
    }
}
```

[<< Config](../docs/004_config.md) | [Code Generator >>](../docs/006_code_generator.md)
