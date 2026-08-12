<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleC;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function stringValueC1(): string
    {
        return $this->getFactory()
            ->createStringC1()
            ->value();
    }

    public function stringValueC2(): string
    {
        return $this->getFactory()
            ->createStringC2()
            ->value();
    }
}
