<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugGraphCheck\CycleA;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Console\DebugGraphCheck\CycleB\Facade as CycleBFacade;

/**
 * Half of a deliberate two-module cycle: CycleA imports CycleB, and CycleB
 * imports CycleA. The graph check exists to find exactly this.
 *
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function name(): string
    {
        return $this->getFactory()->createName();
    }

    public function reachesB(): string
    {
        return CycleBFacade::class;
    }
}
