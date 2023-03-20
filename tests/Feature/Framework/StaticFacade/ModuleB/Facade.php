<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\ModuleB;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function createString(): string
    {
        return $this->getFactory()->createString();
    }
}
