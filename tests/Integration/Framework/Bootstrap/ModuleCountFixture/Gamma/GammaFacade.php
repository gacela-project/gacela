<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ModuleCountFixture\Gamma;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<GammaFactory>
 */
final class GammaFacade extends AbstractFacade
{
    public function name(): string
    {
        return $this->getFactory()->buildName();
    }
}
