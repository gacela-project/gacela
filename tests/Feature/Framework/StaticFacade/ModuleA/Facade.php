<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\ModuleA;

use Gacela\Framework\AbstractFacade;

final class Facade extends AbstractFacade
{
    public function createString(): string
    {
        return $this->getFactory()->createString();
    }
}
