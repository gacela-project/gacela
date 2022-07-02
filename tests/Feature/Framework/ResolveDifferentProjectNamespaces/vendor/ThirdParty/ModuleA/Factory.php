<?php

declare(strict_types = 1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleA;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;

class Factory extends AbstractFactory
{
    public function createStringA1(): StringValueInterface
    {
        return new StringValue('Hi, from vendor\ThirdParty\ModuleA::StringA1');
    }

    public function createStringA2(): StringValueInterface
    {
        return new StringValue('Hi, from vendor\ThirdParty\ModuleA::StringA2');
    }
}
