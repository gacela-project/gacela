<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Gamma;

use Gacela\Framework\AbstractFacade;

/**
 * The leaf: it imports no sibling, so it is the module every "depends only on"
 * assertion can hold for with an empty list.
 */
final class GammaFacade extends AbstractFacade
{
    public function name(): string
    {
        return self::class;
    }
}
