<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomResolvableTypes\ModuleA;

use Gacela\Framework\AbstractFacade;

/**
 * @method FactoryModuleA getFactory()
 */
final class FacadeModuleA extends AbstractFacade
{
    public function doSomething(): array
    {
        return $this->getFactory()->getArrayConfigAndProvidedDependency();
    }
}
