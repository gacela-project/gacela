<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingAbstractGacelaClassesByDefault\Module;

use Gacela\Framework\AbstractFacade;

final class Facade extends AbstractFacade
{
    public function getAppRootDir(): string
    {
        return $this->getFactory()->getConfig()->getAppRootDir();
    }
}
