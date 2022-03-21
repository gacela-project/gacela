<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\CachingResolvableClasses\ModuleB;

use Gacela\Framework\AbstractFacade;

/**
 * @method FactoryModuleB getFactory()
 */
final class FacadeModuleB extends AbstractFacade
{
    public function loadGacelaCacheFile(): array
    {
        return $this->getFactory()->getArrayConfigAndProvidedDependency();
    }
}
