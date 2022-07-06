<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\src\Secondary\ModuleB;

use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleB\Factory as ThirdPartyFactory;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;

final class Factory extends ThirdPartyFactory
{
    public function createStringB1(): StringValueInterface
    {
        return new StringValue('Overridden, from src\CompanyB\ModuleB');
    }
}
