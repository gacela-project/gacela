<?php

declare(strict_types = 1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\Persona\ModuleA;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;

class Factory extends AbstractFactory
{
    public function createStringA(): StringValueInterface
    {
        return new StringValue('Hi, from vendor\Persona\ModuleA::StringA');
    }

    public function createStringB(): StringValueInterface
    {
        return new StringValue('Hi, from vendor\Persona\ModuleA::StringB');
    }
}
