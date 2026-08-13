<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ModuleCountFixture\Beta;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<BetaFactory>
 */
final class BetaFacade extends AbstractFacade
{
    public function name(): string
    {
        return $this->getFactory()->buildName();
    }
}
