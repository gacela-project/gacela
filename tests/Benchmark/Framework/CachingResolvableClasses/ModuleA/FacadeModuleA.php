<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\CachingResolvableClasses\ModuleA;

use Gacela\Framework\AbstractFacade;

/**
 * @method FactoryModuleA getFactory()
 */
final class FacadeModuleA extends AbstractFacade
{
    public function loadGacelaCacheFile(): array
    {
        return $this->getFactory()->getArrayConfigAndProvidedDependency();
    }
}
