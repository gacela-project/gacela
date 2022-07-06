<?php

declare(strict_types = 1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleA;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
class Facade extends AbstractFacade
{
    public function stringValueA1(): string
    {
        return $this->getFactory()
            ->createStringA1()
            ->value();
    }

    public function stringValueA2(): string
    {
        return $this->getFactory()
            ->createStringA2()
            ->value();
    }
}
