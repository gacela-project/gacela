<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleModuleGreeting;

use Gacela\AbstractFactory;
use GacelaTest\Fixtures\ExampleModuleCalculator\ExampleModuleCalculatorFacadeInterface;
use GacelaTest\Fixtures\ExampleModuleGreeting\Greeter\Greeter;
use GacelaTest\Fixtures\ExampleModuleGreeting\Greeter\GreeterInterface;

/**
 * @method ExampleModuleGreetingConfig getConfig()
 */
final class ExampleModuleGreetingFactory extends AbstractFactory
{
    public function createGreeter(): GreeterInterface
    {
        return new Greeter(
            $this->getExampleModuleCalculatorFacade()
        );
    }

    private function getExampleModuleCalculatorFacade(): ExampleModuleCalculatorFacadeInterface
    {
        return $this->getProvidedDependency(ExampleModuleGreetingDependencyProvider::FACADE_CALCULATOR);
    }
}
