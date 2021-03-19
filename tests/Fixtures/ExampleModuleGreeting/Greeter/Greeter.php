<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleModuleGreeting\Greeter;

use GacelaTest\Fixtures\ExampleModuleCalculator\ExampleModuleCalculatorFacadeInterface;

final class Greeter implements GreeterInterface
{
    private ExampleModuleCalculatorFacadeInterface $calculatorFacade;

    public function __construct(ExampleModuleCalculatorFacadeInterface $calculatorFacade)
    {
        $this->calculatorFacade = $calculatorFacade;
    }

    public function greet(string $name): string
    {
        return sprintf('Hello, %s! 2 + 2 = %d', $name, $this->calculatorFacade->add(2, 2));
    }
}
