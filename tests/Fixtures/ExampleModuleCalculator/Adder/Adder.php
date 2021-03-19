<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleModuleCalculator\Adder;

final class Adder implements AdderInterface
{
    public function add(int ...$numbers): int
    {
        return array_sum($numbers);
    }
}
