<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes\ModuleC;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function doSomething(): array
    {
        return $this->getFactory()->getArrayConfigAndProvidedDependency();
    }
}
