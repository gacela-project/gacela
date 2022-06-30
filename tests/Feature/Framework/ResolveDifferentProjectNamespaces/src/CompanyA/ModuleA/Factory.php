<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\src\CompanyA\ModuleA;

use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\Persona\ModuleA\Factory as PersonaFactory;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;

final class Factory extends PersonaFactory
{
    public function createStringA(): StringValueInterface
    {
        return new StringValue('Overridden, from src\CompanyA\ModuleA::StringA');
    }
}
