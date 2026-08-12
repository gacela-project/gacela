<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleC;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;

class Factory extends AbstractFactory
{
    public function createStringC1(): StringValueInterface
    {
        return new StringValue('Hi, from vendor\ThirdParty\ModuleC::StringC1');
    }

    public function createStringC2(): StringValueInterface
    {
        return new StringValue('Hi, from vendor\ThirdParty\ModuleC::StringC2');
    }
}
