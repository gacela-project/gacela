<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleModuleCalculator\Adder;

interface AdderInterface
{
    public function add(int ...$numbers): int;
}
