<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleG;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function loadGacelaCacheFile(): array
    {
        return $this->getFactory()->getArrayConfigAndProvidedDependency();
    }
}
