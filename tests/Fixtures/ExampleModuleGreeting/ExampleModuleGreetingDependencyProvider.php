<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleModuleGreeting;

use Gacela\AbstractDependencyProvider;
use Gacela\Container\Container;
use GacelaTest\Fixtures\ExampleModuleCalculator\ExampleModuleCalculatorFacade;

final class ExampleModuleGreetingDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_CALCULATOR = 'FACADE_CALCULATOR';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCalculator($container);
    }

    private function addFacadeCalculator(Container $container): void
    {
        $container->set(self::FACADE_CALCULATOR, fn () => new ExampleModuleCalculatorFacade());
    }
}
