<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugGraph\ModuleB;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function name(): string
    {
        return $this->getFactory()->createName();
    }
}
