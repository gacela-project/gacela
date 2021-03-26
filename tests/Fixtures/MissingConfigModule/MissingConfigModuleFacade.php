<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\MissingConfigModule;

use Gacela\AbstractFacade;

/**
 * @method MissingConfigModuleFactory getFactory()
 */
final class MissingConfigModuleFacade extends AbstractFacade
{
    public function error(): void
    {
        $this->getFactory()->createDomainService();
    }
}
