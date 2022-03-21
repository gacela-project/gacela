<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CachingResolvableClasses\ModuleB;

use Gacela\Framework\AbstractFacade;

/**
 * @method FactoryB getFactory()
 */
final class Facade extends AbstractFacade
{
    public function loadGacelaCacheFile(): array
    {
        return $this->getFactory()->getArrayConfigAndProvidedDependency();
    }
}
