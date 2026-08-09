<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\src\Main\ModuleA;

use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleA\Factory as ThirdPartyFactory;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use Override;

final class Factory extends ThirdPartyFactory
{
    #[Override]
    public function createStringA1(): StringValueInterface
    {
        return new StringValue('Overridden, from src\CompanyA\ModuleA::StringA');
    }
}
