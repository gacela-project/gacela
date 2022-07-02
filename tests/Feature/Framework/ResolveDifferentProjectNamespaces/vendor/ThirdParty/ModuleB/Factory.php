<?php

declare(strict_types = 1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleB;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;

class Factory extends AbstractFactory
{
    public function createStringB1(): StringValueInterface
    {
        return new StringValue('Hi, from vendor\ThirdParty\ModuleB::StringB1');
    }
}
