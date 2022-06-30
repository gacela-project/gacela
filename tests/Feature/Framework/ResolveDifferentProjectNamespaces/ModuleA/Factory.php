<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\ModuleA;

use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\Persona\ModuleA\Factory as PersonaFactory;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;

final class Factory extends PersonaFactory
{
    public function createString(): StringValueInterface
    {
        return new StringValue('Overridden string from ModuleA');
    }
}
