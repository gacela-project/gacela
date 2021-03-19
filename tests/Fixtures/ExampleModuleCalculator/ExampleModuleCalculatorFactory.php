<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleModuleCalculator;

use Gacela\AbstractFactory;
use GacelaTest\Fixtures\ExampleModuleCalculator\Adder\Adder;
use GacelaTest\Fixtures\ExampleModuleCalculator\Adder\AdderInterface;

/**
 * @method ExampleModuleCalculatorConfig getConfig()
 */
final class ExampleModuleCalculatorFactory extends AbstractFactory
{
    public function createAdder(): AdderInterface
    {
        return new Adder();
    }
}
