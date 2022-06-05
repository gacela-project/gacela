<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\ClassNameCache\ModuleA;

use Gacela\Framework\AbstractFacade;

/**
 * @method FactoryA getFactory()
 */
final class Facade extends AbstractFacade
{
    public function loadGacelaCacheFile(): array
    {
        return $this->getFactory()->getArrayConfigAndProvidedDependency();
    }
}
