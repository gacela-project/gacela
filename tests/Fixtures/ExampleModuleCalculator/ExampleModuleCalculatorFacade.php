<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleModuleCalculator;

use Gacela\AbstractFacade;

/**
 * @method ExampleModuleCalculatorFactory getFactory()
 */
final class ExampleModuleCalculatorFacade extends AbstractFacade implements ExampleModuleCalculatorFacadeInterface
{
    public function add(int ...$numbers): int
    {
        return $this->getFactory()
            ->createAdder()
            ->add(...$numbers);
    }
}
