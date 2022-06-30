<?php

declare(strict_types = 1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\Persona\ModuleA;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;

class Factory extends AbstractFactory
{
    public function createString(): StringValueInterface
    {
        return new StringValue('Hi, from vendor\Persona\ModuleA');
    }
}
