<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\MissingFactoryModule;

use Gacela\AbstractFacade;

final class MissingFactoryModuleFacade extends AbstractFacade
{
    public function error(): void
    {
        $this->getFactory();
    }
}
