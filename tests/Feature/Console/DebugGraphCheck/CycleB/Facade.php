<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugGraphCheck\CycleB;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Console\DebugGraphCheck\CycleA\Facade as CycleAFacade;

/**
 * The other half of the deliberate cycle. See CycleA\Facade.
 *
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function name(): string
    {
        return $this->getFactory()->createName();
    }

    public function reachesA(): string
    {
        return CycleAFacade::class;
    }
}
