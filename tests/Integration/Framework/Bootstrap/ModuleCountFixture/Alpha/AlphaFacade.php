<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ModuleCountFixture\Alpha;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<AlphaFactory>
 */
final class AlphaFacade extends AbstractFacade
{
    public function name(): string
    {
        return $this->getFactory()->buildName();
    }
}
