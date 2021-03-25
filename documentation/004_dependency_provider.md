[Back to the index](../documentation)

# Dependency Provider

This is the place where you can define the dependencies that a particular module has with other modules.

In this example you can see that in the `Calculator` we have a service (`Adder`) which needs the
`AnotherModuleFacade` as a dependency. In this case, we can define the dependency inside the
`CalculatorDependencyProvider`.

```php
# src/Calculator/CalculatorFactory.php
final class CalculatorFactory extends AbstractFactory
{
    public function createAdder(): AdderInterface
    {
        return new Adder(
            $this->getAnotherModuleFacade()
        );
    }
    
    private function getAnotherModuleFacade(): AnotherModuleFacade
    {
        return $this->getProvidedDependency(CalculatorDependencyProvider::FACADE_ANOTHER_MODULE);
    }
}
```

```php
# src/Calculator/CalculatorDependencyProvider.php
final class CalculatorDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_ANOTHER_MODULE = 'FACADE_ANOTHER_MODULE';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeAnotherModule($container);
    }

    private function addFacadeAnotherModule(Container $container): void
    {
        $container->set(self::FACADE_ANOTHER_MODULE, fn () => new AnotherModuleFacade());
    }
}
```
