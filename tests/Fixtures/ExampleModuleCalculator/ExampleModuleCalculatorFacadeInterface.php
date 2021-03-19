<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleModuleCalculator;

interface ExampleModuleCalculatorFacadeInterface
{
    public function add(int ...$numbers): int;
}
