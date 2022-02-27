<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomGacelaFileSuffix\LocalConfig;

use Gacela\Framework\AbstractFacade;

/**
 * @method FactCustom getFactory()
 */
final class FacaCustom extends AbstractFacade
{
    public function doSomething(): array
    {
        return $this->getFactory()->getArrayConfigAndProvidedDependency();
    }
}
