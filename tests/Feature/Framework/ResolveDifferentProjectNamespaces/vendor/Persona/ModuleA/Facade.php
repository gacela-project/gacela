<?php

declare(strict_types = 1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\Persona\ModuleA;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
class Facade extends AbstractFacade implements FacadeInterface
{
    public function sayHiA(): string
    {
        return $this->getFactory()
            ->createStringA()
            ->value();
    }

    public function sayHiB(): string
    {
        return $this->getFactory()
            ->createStringB()
            ->value();
    }
}
