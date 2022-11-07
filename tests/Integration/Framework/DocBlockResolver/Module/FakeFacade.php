<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolver\Module;

use Gacela\Framework\AbstractFacade;

/**
 * @method FakeFactory getFactory()
 */
final class FakeFacade extends AbstractFacade
{
    public function doString(): string
    {
        return $this->getFactory()->createString();
    }
}
