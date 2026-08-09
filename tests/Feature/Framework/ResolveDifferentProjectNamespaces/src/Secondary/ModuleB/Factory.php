<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\src\Secondary\ModuleB;

use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleB\Factory as ThirdPartyFactory;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use Override;

final class Factory extends ThirdPartyFactory
{
    #[Override]
    public function createStringB1(): StringValueInterface
    {
        return new StringValue('Overridden, from src\CompanyB\ModuleB');
    }
}
