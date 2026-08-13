<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Sealed;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<SealedFactory>
 */
final class SealedFacade extends AbstractFacade
{
    public function greet(): string
    {
        return $this->getFactory()->createGreeter()->greet();
    }
}
