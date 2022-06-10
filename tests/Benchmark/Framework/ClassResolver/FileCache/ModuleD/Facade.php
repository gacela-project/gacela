<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleD;

use Gacela\Framework\AbstractFacade;

/**
 * @method FactoryD getFactory()
 */
final class Facade extends AbstractFacade
{
    public function loadGacelaCacheFile(): array
    {
        return $this->getFactory()->getArrayConfigAndProvidedDependency();
    }
}
