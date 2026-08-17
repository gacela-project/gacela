<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Alpha;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Beta\BetaFacade;
use GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Gamma\GammaFacade;

/**
 * The Beta import on line 8 is the edge every failure message in
 * {@see \GacelaTest\Integration\Console\Testing\ModuleAssertionsTest} points at,
 * so the two imports below are ordered rather than alphabetical by accident.
 *
 * The names are returned rather than merely imported because cs-fixer removes
 * an import nothing uses, which would take the edge with it.
 */
final class AlphaFacade extends AbstractFacade
{
    public function neighbours(): string
    {
        return BetaFacade::class . GammaFacade::class;
    }
}
