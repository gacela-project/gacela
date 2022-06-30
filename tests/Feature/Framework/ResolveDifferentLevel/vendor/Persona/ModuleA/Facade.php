<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentLevel\vendor\Persona\ModuleA;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
class Facade extends AbstractFacade implements FacadeInterface
{
    public function sayHi(): string
    {
        return $this->getFactory()
            ->createString()
            ->value();
    }
}
