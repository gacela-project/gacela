<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\GlobalServices\Counter;

use Gacela\Framework\AbstractFactory;

final class CounterFactory extends AbstractFactory
{
    public function createTotal(): string
    {
        return 'counter';
    }
}
