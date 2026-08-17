<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Beta;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Alpha\AlphaFacade;

/**
 * The import back into Alpha, which is what makes the pair a cycle.
 */
final class BetaFacade extends AbstractFacade
{
    public function neighbour(): string
    {
        return AlphaFacade::class;
    }
}
