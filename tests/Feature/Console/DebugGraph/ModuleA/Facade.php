<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugGraph\ModuleA;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Console\DebugGraph\ModuleB\Facade as ModuleBFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function combined(): string
    {
        return (new ModuleBFacade())->name() . '+a';
    }
}
