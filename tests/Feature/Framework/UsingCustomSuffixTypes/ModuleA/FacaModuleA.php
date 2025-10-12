<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes\ModuleA;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<FactModuleA>
 */
final class FacaModuleA extends AbstractFacade
{
    public function doSomething(): array
    {
        return $this->getFactory()->getArrayConfigAndProvidedDependency();
    }
}
