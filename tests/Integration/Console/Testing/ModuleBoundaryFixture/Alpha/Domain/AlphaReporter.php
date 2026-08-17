<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Alpha\Domain;

use GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Beta\BetaFacade;

/**
 * A second file in Alpha reaching into Beta.
 *
 * One edge written twice, which is what a boundary failure normally looks like:
 * a reader told only about the first file fixes one import and finds the check
 * still red. The evidence has to list both.
 *
 * The name is returned rather than merely imported because cs-fixer removes an
 * import nothing uses, which would take the second edge with it.
 */
final class AlphaReporter
{
    public function neighbour(): string
    {
        return BetaFacade::class;
    }
}
