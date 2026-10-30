<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\GlobalServices\Greeter;

use Gacela\Framework\AbstractFactory;

final class GreeterFactory extends AbstractFactory
{
    public function createTotal(): string
    {
        return 'total';
    }
}
