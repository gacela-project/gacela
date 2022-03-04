<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomResolvableTypes\ModuleB;

use Gacela\Framework\AbstractFacade;

/**
 * @method FactoryModuleB getFactory()
 */
final class FacadeModuleB extends AbstractFacade
{
    public function doSomething(): array
    {
        return $this->getFactory()->getArrayConfigAndProvidedDependency();
    }
}
