<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleB;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
class Facade extends AbstractFacade
{
    public function stringValueB1(): string
    {
        return $this->getFactory()
            ->createStringB1()
            ->value();
    }
}
