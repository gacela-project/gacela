<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules\PillarFixtures\DefinedModule;

use Gacela\Framework\AbstractFacade;

/**
 * @method DefinedModuleFactory getFactory()
 */
final class DefinedModuleFacade extends AbstractFacade
{
    public function contract(): DefinedContract
    {
        return $this->getFactory()->contract;
    }
}
