<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\ClassNameCache\ModuleE;

use Gacela\Framework\AbstractFacade;

/**
 * @method FactoryE getFactory()
 */
final class Facade extends AbstractFacade
{
    public function loadGacelaCacheFile(): array
    {
        return $this->getFactory()->getArrayConfigAndProvidedDependency();
    }
}
