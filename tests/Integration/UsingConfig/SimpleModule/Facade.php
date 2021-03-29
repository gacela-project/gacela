<?php

declare(strict_types=1);

namespace GacelaTest\Integration\UsingConfig\SimpleModule;

use Gacela\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function doSomething(): int
    {
        return $this->getFactory()->getNumber();
    }
}
