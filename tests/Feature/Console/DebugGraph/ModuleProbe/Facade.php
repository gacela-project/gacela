<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugGraph\ModuleProbe;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Console\DebugGraph\ModuleB\Facade as ModuleBFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function probe(): string
    {
        return (new ModuleBFacade())->name() . '+probe';
    }
}
