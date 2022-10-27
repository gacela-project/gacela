<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\Module;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function doString(): string
    {
        return $this->getFactory()->createString();
    }
}
